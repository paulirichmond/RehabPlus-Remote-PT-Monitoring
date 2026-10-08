<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Appointment Details</title>
    <style>
        body { margin: 0; font-family: Arial, sans-serif; background: #f3f4f6; color: #1f2937; }
        .shell { max-width: 800px; margin: 0 auto; padding: 24px; }
        .card { background: white; border-radius: 12px; padding: 24px; box-shadow: 0 6px 18px rgba(0,0,0,0.05); }
        .meta { display: grid; grid-template-columns: 1fr 1fr; gap: 16px; }
        a { color: #2563eb; text-decoration: none; }
    </style>
</head>
<body>
    <div class="shell">
        <a href="{{ route('appointments.index') }}">← Back to appointments</a>
        <div class="card" style="margin-top: 16px;">
            <h1>{{ $appointment->session }}</h1>
            <p><strong>Patient:</strong> {{ $appointment->patient }}</p>
            <p><strong>Therapist:</strong> {{ $appointment->therapist }}</p>
            <div class="meta">
                <div>
                    <p><strong>Date:</strong> {{ $appointment->date }}</p>
                    <p><strong>Time:</strong> {{ $appointment->time }}</p>
                </div>
                <div>
                    <p><strong>Condition:</strong> {{ $appointment->patient_condition }}</p>
                    <p><strong>Status:</strong> {{ $appointment->status }}</p>
                </div>
            </div>
            <p><strong>Contact:</strong> {{ $appointment->contact }}</p>
            <p><strong>Notes:</strong> {{ $appointment->notes ?? 'No notes available.' }}</p>
        </div>
    </div>
</body>
</html>
