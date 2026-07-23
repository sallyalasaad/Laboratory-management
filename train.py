import os
import pickle
import pandas as pd
from statsmodels.tsa.holtwinters import ExponentialSmoothing
import warnings

warnings.filterwarnings("ignore")

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

        days_ramadan = max((overlap_end - overlap_start).days + 1, 0)
        ramadan_fraction = days_ramadan / month_end.day

        if ramadan_fraction > 0:
            return row['quantity'] / (1 + 2 * ramadan_fraction)

    return row['quantity']

def train_and_save_models():
    # إنشاء مجلد حفظ النماذج إذا لم يكن موجوداً
    os.makedirs("models", exist_ok=True)

    # مسار ملف البيانات داخل المشروع
    base_dir = os.path.dirname(__file__)
    csv_path = os.path.join(base_dir, 'cheese_monthly.csv')

    if not os.path.exists(csv_path):
        raise FileNotFoundError(f"CSV file not found: {csv_path}")

    data = pd.read_csv(csv_path)
    data['month'] = pd.to_datetime(data['month'])

    # تنظيف البيانات من تأثير رمضان
    data['quantity_base'] = data.apply(remove_past_ramadan_effect, axis=1)

    last_month = data['month'].max()
    unique_sizes = data['size'].unique()

    # تدريب وحفظ نموذج لكل حجم
    for size in unique_sizes:
        df = data[data['size'] == size].copy()
        df.set_index('month', inplace=True)
        # للتأكد من التردد الشهري
        df = df.asfreq('MS')

        model = ExponentialSmoothing(
            df['quantity_base'],
            trend='add',
            seasonal='add',
            seasonal_periods=12
        )
        fit_model = model.fit()

        # حفظ النموذج المدرب
        model_filename = f"models/es_model_{size}.pkl"
        with open(model_filename, 'wb') as f:
            pickle.dump(fit_model, f)

    # حفظ البيانات الوصفية (مثل آخر شهر تم التدريب عليه وقائمة الأحجام)
    metadata = {
        "last_month": last_month,
        "sizes": list(unique_sizes)
    }
    with open("models/metadata.pkl", 'wb') as f:
        pickle.dump(metadata, f)

    print("Information: Models trained and saved successfully!")

if __name__ == "__main__":
    train_and_save_models()
from fastapi import FastAPI, HTTPException

app = FastAPI()

@app.get("/forecast")
async def forecast(target_month: str):
    try:
        # هنا ضع الكود الخاص بك الذي يقوم بعملية التوقعات
        ...
    except Exception as e:
        raise HTTPException(status_code=500, detail=str(e))
