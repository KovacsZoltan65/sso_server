<!DOCTYPE html>
<html lang="hu">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Logout in progress</title>
    <style>
        body {
            font-family: Arial, sans-serif;
            margin: 0;
            background: #f5f7fb;
            color: #1f2937;
        }
        .page {
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 2rem;
        }
        .card {
            max-width: 36rem;
            width: 100%;
            background: #fff;
            border-radius: 1rem;
            box-shadow: 0 20px 45px rgba(15, 23, 42, 0.12);
            padding: 2rem;
        }
        .muted {
            color: #4b5563;
            line-height: 1.5;
        }
        .hidden-iframe {
            width: 0;
            height: 0;
            border: 0;
            position: absolute;
        }
    </style>
</head>
<body>
    <div class="page">
        <div class="card">
            <h1>Kijelentkeztetes folyamatban</h1>
            <p class="muted">
                A kozponti munkamenet lezarult. A kapcsolodo kliensek front-channel logout jelzest kapnak, ezutan a rendszer tovabblep.
            </p>
            <noscript>
                <p class="muted">
                    A folytatashoz JavaScript szukseges. Ha nem tortenik automatikus tovabblepes, nyisd meg a kovetkezo oldalt:
                    <a href="{{ $finalRedirectUrl }}">{{ $finalRedirectUrl }}</a>
                </p>
            </noscript>
        </div>
    </div>

    @foreach ($frontChannelTargets as $target)
        <iframe
            class="hidden-iframe"
            src="{{ $target['logout_url'] }}"
            title="front-channel-logout-{{ $target['client_public_id'] }}"
            loading="eager"
            referrerpolicy="no-referrer"
        ></iframe>
    @endforeach

    <script>
        window.setTimeout(function () {
            window.location.replace(@json($finalRedirectUrl));
        }, 350);
    </script>
</body>
</html>
