from fastapi import FastAPI, HTTPException
import pandas as pd
import pickle
import os
import warnings

warnings.filterwarnings("ignore")

app = FastAPI()

# =========================
# Config
# =========================
BATCH_OUTPUT = 750

RECIPE = {
    "Kashkaval Milk (kg)": 250,
    "Butter (kg)": 100,
    "Salt (kg)": 150,
    "Flavor (kg)": 10,
    "Acid (kg)": 100,
    "Preservatives (kg)": 5,
    "Water (kg)": 130
}

ramadan_dates = {
    2023: ["2023-03-23", "2023-04-20"],
    2024: ["2024-03-11", "2024-04-09"],
    2025: ["2025-03-01", "2025-03-30"],
    2026: ["2026-02-18", "2026-03-19"],
    2027: ["2027-02-08", "2027-03-09"],
    2028: ["2028-01-28", "2028-02-26"]
}

size_to_kg = {
    "350g": 0.35,
    "1kg": 1,
    "3kg": 3,
    "5kg": 5
}

# =========================
# Load models
# =========================
def load_models_and_metadata():
    metadata_path = "models/metadata.pkl"

    if not os.path.exists(metadata_path):
        raise RuntimeError("Models not trained yet. Run train.py first.")

    with open(metadata_path, "rb") as f:
        metadata = pickle.load(f)

    if "sizes" not in metadata:
        raise RuntimeError("Metadata missing 'sizes' key")

    models = {}

    for size in metadata["sizes"]:
        model_path = f"models/es_model_{size}.pkl"

        if not os.path.exists(model_path):
            raise RuntimeError(f"Missing model file: {model_path}")

        with open(model_path, "rb") as f:
            models[size] = pickle.load(f)

    return models, metadata


# =========================
# Startup load
# =========================
try:
    MODELS, METADATA = load_models_and_metadata()
    print("Models loaded successfully")
except Exception as e:
    import traceback
    traceback.print_exc()
    raise
# =========================
# API
# =========================
@app.get("/forecast")
def forecast(target_month: str):

    if not MODELS or not METADATA:
        raise HTTPException(
            status_code=500,
            detail="Models are not loaded. Run train.py first."
        )

    # Validate date
    try:
        target_month_dt = pd.to_datetime(target_month)
    except Exception:
        raise HTTPException(
            status_code=400,
            detail="Invalid date format. Use YYYY-MM"
        )

    # Validate metadata
    if "last_month" not in METADATA:
        raise HTTPException(
            status_code=500,
            detail="Metadata missing last_month"
        )

    last_month = METADATA["last_month"]

    # Steps calculation
    steps = (
        (target_month_dt.year - last_month.year) * 12 +
        (target_month_dt.month - last_month.month)
    )

    if steps <= 0:
        return {
            "error": f"Target month must be after {last_month.strftime('%Y-%m')}"
        }

    total_cheese_kg = 0
    results = []

    # =========================
    # Forecast per size
    # =========================
    for size, model in MODELS.items():

        forecast_series = model.forecast(steps=steps)

        # safe last value
        forecast_value = float(forecast_series.iloc[-1])
        # Ramadan effect
        year = target_month_dt.year
        month_start = target_month_dt
        month_end = target_month_dt + pd.offsets.MonthEnd(0)

        ramadan_fraction = 0.0

        if year in ramadan_dates:
            ram_start = pd.to_datetime(ramadan_dates[year][0])
            ram_end = pd.to_datetime(ramadan_dates[year][1])

            overlap_start = max(month_start, ram_start)
            overlap_end = min(month_end, ram_end)

            days_ramadan = max((overlap_end - overlap_start).days + 1, 0)

            ramadan_fraction = days_ramadan / max(month_end.day, 1)

            if ramadan_fraction > 0:
                forecast_value *= (1 + 2 * ramadan_fraction)

        cheese_kg = forecast_value * size_to_kg.get(size, 1)
        total_cheese_kg += cheese_kg

        results.append({
            "size": size,
            "forecast": round(forecast_value, 2),
            "cheese_kg": round(cheese_kg, 2)
        })

    # =========================
    # Production planning
    # =========================
    full_batches = int(total_cheese_kg // BATCH_OUTPUT)
    remainder = total_cheese_kg % BATCH_OUTPUT

    batches = full_batches + (1 if remainder > 0 else 0)

    monthly_capacity = (full_batches * BATCH_OUTPUT) + remainder
    surplus = monthly_capacity - total_cheese_kg

    materials = {
        material: qty * batches
        for material, qty in RECIPE.items()
    }

    schedule = (
        f"1 batch every {round(30 / batches, 1)} days"
        if batches > 0
        else "No production scheduled"
    )

    return {
        "month": target_month,
        "production_kg": round(total_cheese_kg, 2),
        "batches": batches,
        "monthly_capacity": round(monthly_capacity, 2),
        "surplus": round(surplus, 2),
        "schedule": schedule,
        "materials": materials,
        "forecast": results
    }
