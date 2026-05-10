from fastapi import FastAPI
import pandas as pd
from statsmodels.tsa.holtwinters import ExponentialSmoothing
import warnings
import math

warnings.filterwarnings("ignore")

app = FastAPI()

# 🔴 سعة الطبخة الواحدة
BATCH_OUTPUT = 750

# 🔴 وصفة الطبخة
RECIPE = {
    "Kashkaval Milk (kg)": 250,
    "Butter (kg)": 100,
    "Salt (kg)": 150,
    "Flavor (kg)": 10,
    "Acid (kg)": 100,
    "Preservatives (kg)": 5,
    "Water (kg)": 130
}

# رمضان
ramadan_dates = {
    2023: ["2023-03-23", "2023-04-20"],
    2024: ["2024-03-11", "2024-04-09"],
    2025: ["2025-03-01", "2025-03-30"],
    2026: ["2026-02-18", "2026-03-19"],
    2027: ["2027-02-08", "2027-03-09"],
    2028: ["2028-01-28", "2028-02-26"]
}


def remove_past_ramadan_effect(row):
    year = row['month'].year
    month_start = row['month']
    month_end = row['month'] + pd.offsets.MonthEnd(0)

    if year in ramadan_dates:
        ram_start = pd.to_datetime(ramadan_dates[year][0])
        ram_end = pd.to_datetime(ramadan_dates[year][1])

        overlap_start = max(month_start, ram_start)
        overlap_end = min(month_end, ram_end)

        days_ramadan = max(
            (overlap_end - overlap_start).days + 1,
            0
        )

        ramadan_fraction = (
            days_ramadan / month_end.day
        )

        if ramadan_fraction > 0:
            return row['quantity'] / (
                1 + 2 * ramadan_fraction
            )

    return row['quantity']


@app.get("/forecast")
def forecast(target_month: str):

    data = pd.read_csv(
        r"D:\Laboratory-management\app\cheese_monthly.csv"
    )

    data['month'] = pd.to_datetime(data['month'])

    data['quantity_base'] = data.apply(
        remove_past_ramadan_effect,
        axis=1
    )

    size_to_kg = {
        "350g": 0.35,
        "1kg": 1,
        "3kg": 3,
        "5kg": 5
    }

    total_cheese_kg = 0
    results = []

    last_month = data['month'].max()

    target_month_dt = pd.to_datetime(
        target_month
    )

    steps = (
        (target_month_dt.year -
         last_month.year) * 12
        +
        (target_month_dt.month -
         last_month.month)
    )

    if steps <= 0:
        return {
            "error":
            "Target month already exists"
        }

    for size in data['size'].unique():

        df = data[
            data['size'] == size
        ].copy()

        df.set_index(
            'month',
            inplace=True
        )

        model = ExponentialSmoothing(
            df['quantity_base'],
            trend='add',
            seasonal='add',
            seasonal_periods=12
        )

        fit = model.fit()

        forecast_value = fit.forecast(
            steps=steps
        ).iloc[-1]

        # تأثير رمضان
        ramadan_fraction = 0.0
        year = target_month_dt.year

        month_start = target_month_dt
        month_end = (
            target_month_dt
            + pd.offsets.MonthEnd(0)
        )

        if year in ramadan_dates:

            ram_start = pd.to_datetime(
                ramadan_dates[year][0]
            )

            ram_end = pd.to_datetime(
                ramadan_dates[year][1]
            )

            overlap_start = max(
                month_start,
                ram_start
            )

            overlap_end = min(
                month_end,
                ram_end
            )

            days_ramadan = max(
                (
                    overlap_end
                    - overlap_start
                ).days + 1,
                0
            )

            ramadan_fraction = (
                days_ramadan
                / month_end.day
            )

            if ramadan_fraction > 0:
                forecast_value *= (
                    1 +
                    2 * ramadan_fraction
                )

        cheese_kg = (
            forecast_value
            *
            size_to_kg.get(size, 1)
        )

        total_cheese_kg += cheese_kg

        results.append({
            "size": size,
            "forecast":
                round(forecast_value, 2),
            "cheese_kg":
                round(cheese_kg, 2)
        })

    # ===== خطة الإنتاج (تعديل آخر طبخة فقط) =====

    full_batches = int(total_cheese_kg // BATCH_OUTPUT)
    remainder = total_cheese_kg % BATCH_OUTPUT

    batches = full_batches
    if remainder > 0:
        batches += 1

    monthly_capacity = (full_batches * BATCH_OUTPUT) + remainder

    surplus = monthly_capacity - total_cheese_kg

    materials = {}

    for material, qty in RECIPE.items():
        materials[material] = (
            qty * batches
        )

    return {
        "month": target_month,
        "production_kg":
            round(total_cheese_kg, 2),
        "batches": batches,
        "monthly_capacity":
            monthly_capacity,
        "surplus":
            round(surplus, 2),
        "schedule":
            f"1 batch every {round(30 / batches, 1)} days",
        "materials":
            materials,
        "forecast":
            results
    }
