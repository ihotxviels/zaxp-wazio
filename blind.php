<?php
http_response_code(403);
?>
<!DOCTYPE html>
<html lang="pt-BR">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Acesso Interceptado - UAZAPI Shield</title>
    <style>
        body {
            background-color: #050505;
            color: #ff3333;
            font-family: 'Courier New', Courier, monospace;
            display: flex;
            flex-direction: column;
            align-items: center;
            justify-content: center;
            height: 100vh;
            margin: 0;
            overflow: hidden;
        }

        .container {
            text-align: center;
            border: 1px solid #ff3333;
            padding: 40px;
            background: rgba(255, 0, 0, 0.05);
            box-shadow: 0 0 30px rgba(255, 0, 0, 0.2);
            position: relative;
            z-index: 2;
        }

        h1 {
            font-size: 2.5em;
            text-transform: uppercase;
            letter-spacing: 2px;
            margin-bottom: 20px;
            text-shadow: 0 0 10px rgba(255, 0, 0, 0.5);
        }

        p {
            font-size: 1.2em;
            max-width: 600px;
            margin: 10px auto;
            line-height: 1.5;
        }

        .ip {
            font-weight: bold;
            color: #fff;
            background: #ff3333;
            padding: 2px 8px;
            border-radius: 4px;
            display: inline-block;
        }

        .scanlines {
            position: fixed;
            top: 0;
            left: 0;
            right: 0;
            bottom: 0;
            background: linear-gradient(to bottom, rgba(255, 255, 255, 0), rgba(255, 255, 255, 0) 50%, rgba(0, 0, 0, 0.2) 50%, rgba(0, 0, 0, 0.2));
            background-size: 100% 4px;
            z-index: 10;
            pointer-events: none;
        }

        @keyframes blink {

            0%,
            100% {
                opacity: 1;
            }

            50% {
                opacity: 0;
            }
        }

        .cursor {
            display: inline-block;
            width: 10px;
            height: 1em;
            background: #ff3333;
            animation: blink 1s step-end infinite;
            vertical-align: bottom;
            margin-left: 5px;
        }

        canvas {
            position: absolute;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            z-index: 1;
            opacity: 0.15;
        }
    </style>
</head>

<body>
    <canvas id="matrix"></canvas>
    <div class="scanlines"></div>

    <div class="container">
        <h1>⚠️ Conexão Bloqueada</h1>
        <p>Ação suspeita detectada. O sistema de defesa WAF (Web Application Firewall) interceptou sua requisição.</p>
        <p>Seu IP <span class="ip" id="user-ip">
                <?php echo htmlspecialchars($_SERVER['REMOTE_ADDR'] ?? 'Desconhecido'); ?>
            </span> e Fingerprint foram registrados na Blacklist.</p>
        <p style="margin-top: 30px; font-size: 0.9em; opacity: 0.8;">Gerando loop recursivo de contramedida Tarpit<span
                class="cursor"></span></p>
    </div>

    <script>
        const canvas = document.getElementById('matrix');
        const ctx = canvas.getContext('2d');
        canvas.width = window.innerWidth;
        canvas.height = window.innerHeight;

        const letters = '0123456789ABCDEF!@#$%&*+='.split('');
        const fontSize = 16;
        const columns = canvas.width / fontSize;
        const drops = [];
        for (let x = 0; x < columns; x++) drops[x] = 1;

        function draw() {
            ctx.fillStyle = 'rgba(5, 5, 5, 0.05)';
            ctx.fillRect(0, 0, canvas.width, canvas.height);
            ctx.fillStyle = '#ff3333';
            ctx.font = fontSize + 'px monospace';
            for (let i = 0; i < drops.length; i++) {
                const text = letters[Math.floor(Math.random() * letters.length)];
                ctx.fillText(text, i * fontSize, drops[i] * fontSize);
                if (drops[i] * fontSize > canvas.height && Math.random() > 0.975) drops[i] = 0;
                drops[i]++;
            }
        }
        setInterval(draw, 33);

        setInterval(() => {
            console.log("%cSTOP! SYSTEM LOCKED. UNAUTHORIZED REQUESTS WILL BE TRACED.", "color: red; font-size: 40px; font-weight: bold; text-shadow: 0 0 10px red;");
        }, 50);
    </script>
</body>

</html>