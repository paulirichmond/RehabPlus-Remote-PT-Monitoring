<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Firebase unavailable - RehabPlus</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet" integrity="sha384-QWTKZyjpPEjISv5WaRU9OFeRpok6YctnYmDr5pNlyT2bRjXh0JMhjY6hW+ALEwIH" crossorigin="anonymous">
</head>
<body class="bg-light">
    <div class="container py-5" style="max-width: 640px;">
        <div class="card border-0 shadow-sm">
            <div class="card-body p-4">
                <h1 class="h4 mb-3">RehabPlus can't reach its data right now</h1>
                <p class="text-muted mb-2">The clinic's records are stored in Firebase, and this request couldn't be completed.</p>
                <p class="small text-muted mb-2">{{ $message }}</p>
                @if (! empty($requestId))
                    <p class="small text-muted mb-4">Reference for support: <code>{{ $requestId }}</code></p>
                @else
                    <div class="mb-4"></div>
                @endif
                <a href="{{ url()->current() }}" class="btn btn-primary">Try again</a>
                <a href="{{ url('/dashboard') }}" class="btn btn-outline-secondary ms-2">Dashboard</a>
            </div>
        </div>
    </div>
</body>
</html>
