<?php
require_once 'config/database.php';

// Get Settings for branding
$res_set = mysqli_query($conn, "SELECT * FROM pengaturan");
$sets = [];
while ($r = mysqli_fetch_assoc($res_set)) $sets[$r['nama_setting']] = $r['nilai_setting'];
$school_name = $sets['nama_sekolah'] ?? 'Sekolah';
$favicon = !empty($sets['favicon']) ? BASE_URL . 'uploads/' . $sets['favicon'] : null;

?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Face Detection - <?= htmlspecialchars($school_name) ?></title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;600;700;800&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">

    <!-- Face-API.js -->
    <script src="https://cdn.jsdelivr.net/npm/face-api.js@0.22.2/dist/face-api.min.js"></script>

    <style>
        @keyframes scan {
            0% { top: 0%; opacity: 0; }
            50% { opacity: 1; }
            100% { top: 100%; opacity: 0; }
        }
        .scanner-line {
            position: absolute;
            width: 100%;
            height: 4px;
            background: linear-gradient(to right, transparent, #4f46e5, transparent);
            box-shadow: 0 0 15px #4f46e5;
            z-index: 15;
            animation: scan 3s ease-in-out infinite;
            display: none;
        }
        body { font-family: 'Plus Jakarta Sans', sans-serif; overflow: hidden; background: #000; }
        .video-container {
            position: relative;
            width: 100vw;
            height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
        }
        video {
            position: absolute;
            width: 100%;
            height: 100%;
            object-fit: cover;
        }
        canvas {
            position: absolute;
            z-index: 10;
        }
        .ui-overlay {
            position: absolute;
            top: 0; left: 0; right: 0; bottom: 0;
            z-index: 20;
            pointer-events: none;
            display: flex;
            flex-direction: column;
            justify-content: space-between;
            padding: 2rem;
            background: linear-gradient(to bottom, rgba(0,0,0,0.4) 0%, transparent 20%, transparent 80%, rgba(0,0,0,0.4) 100%);
        }
        .detection-label {
            position: absolute;
            background: rgba(79, 70, 229, 0.9);
            color: white;
            padding: 0.5rem 1rem;
            border-radius: 0.75rem;
            font-weight: 800;
            font-size: 0.875rem;
            text-transform: uppercase;
            letter-spacing: 0.05em;
            box-shadow: 0 10px 15px -3px rgba(0, 0, 0, 0.1);
            white-space: nowrap;
            z-index: 30;
            transform: translate(-50%, -100%);
            margin-top: -10px;
            backdrop-filter: blur(4px);
        }
    </style>
</head>
<body>

    <div class="video-container">
        <video id="video" autoplay muted playsinline class="transform scale-x-[-1]"></video>
        <div class="scanner-line" id="scannerLine"></div>

        <div class="ui-overlay">
            <div class="flex items-center justify-between">
                <div class="flex items-center gap-4">
                    <div class="w-12 h-12 rounded-2xl bg-white/10 backdrop-blur-md flex items-center justify-center border border-white/20">
                        <?php if ($favicon): ?>
                            <img src="<?= $favicon ?>" class="w-7 h-7 object-contain">
                        <?php else: ?>
                            <i class="fa fa-shield-halved text-white text-xl"></i>
                        <?php endif; ?>
                    </div>
                    <div>
                        <h2 class="text-white font-black text-xl tracking-tighter">CAKRA FACE ID</h2>
                        <p class="text-white/50 text-[8px] font-bold uppercase tracking-[0.2em]"><?= htmlspecialchars($school_name) ?></p>
                    </div>
                </div>

                <div id="statusIndicator" class="px-4 py-2 rounded-full bg-white/10 backdrop-blur-md border border-white/20 flex items-center gap-2">
                    <div class="w-2 h-2 rounded-full bg-amber-400 animate-pulse"></div>
                    <span class="text-white/80 text-[10px] font-black uppercase tracking-widest">Memuat AI...</span>
                </div>
            </div>

            <div class="flex flex-col items-center">
                <div class="w-full max-w-xs p-4 rounded-3xl bg-white/10 backdrop-blur-md border border-white/20 text-center">
                    <p class="text-white/60 text-[9px] font-bold uppercase tracking-widest mb-1">Status Deteksi</p>
                    <div id="detectionResult" class="text-white font-black italic uppercase text-sm tracking-tighter">Mencari Wajah...</div>
                </div>
            </div>
        </div>
    </div>

    <script>
        const video = document.getElementById('video');
        const statusIndicator = document.getElementById('statusIndicator');
        const detectionResult = document.getElementById('detectionResult');
        const scannerLine = document.getElementById('scannerLine');

        // Alternative CDNs:
        // 1. https://cdn.jsdelivr.net/gh/justadudewhohacks/face-api.js@master/weights
        // 2. https://raw.githubusercontent.com/justadudewhohacks/face-api.js/master/weights (Raw GitHub - sometimes blocked)
        // 3. Hosting locally (best but requires assets in the repo)

        const MODEL_URL = '<?= BASE_URL ?>models';

        async function init() {
            try {
                statusIndicator.innerHTML = '<div class="w-2 h-2 rounded-full bg-amber-400 animate-pulse"></div><span class="text-white/80 text-[10px] font-black uppercase tracking-widest">Memuat AI...</span>';

                console.log("Loading Models from:", MODEL_URL);

                await Promise.all([
                    faceapi.nets.ssdMobilenetv1.loadFromUri(MODEL_URL),
                    faceapi.nets.faceLandmark68Net.loadFromUri(MODEL_URL),
                    faceapi.nets.faceRecognitionNet.loadFromUri(MODEL_URL),
                    faceapi.nets.tinyFaceDetector.loadFromUri(MODEL_URL)
                ]);

                statusIndicator.innerHTML = '<div class="w-2 h-2 rounded-full bg-amber-400 animate-pulse"></div><span class="text-white/80 text-[10px] font-black uppercase tracking-widest">Memulai Kamera...</span>';

                startVideo();
            } catch (err) {
                console.error("Initialization failed:", err);
                statusIndicator.innerHTML = '<div class="w-2 h-2 rounded-full bg-rose-500"></div><span class="text-white/80 text-[10px] font-black uppercase tracking-widest">Gagal Memuat AI</span>';
                detectionResult.innerText = 'Error: Cek File Model';
            }
        }

        function startVideo() {
            if (navigator.mediaDevices && navigator.mediaDevices.getUserMedia) {
                navigator.mediaDevices.getUserMedia({
                    video: {
                        facingMode: "user",
                        width: { ideal: 1280 },
                        height: { ideal: 720 }
                    }
                })
                .then(stream => {
                    video.srcObject = stream;
                    video.onloadedmetadata = () => {
                        video.play();
                        scannerLine.style.display = 'block';
                        statusIndicator.innerHTML = '<div class="w-2 h-2 rounded-full bg-emerald-400"></div><span class="text-white/80 text-[10px] font-black uppercase tracking-widest">Sistem Aktif</span>';
                    };
                })
                .catch(err => {
                    console.error("Camera access denied:", err);
                    statusIndicator.innerHTML = '<div class="w-2 h-2 rounded-full bg-rose-500"></div><span class="text-white/80 text-[10px] font-black uppercase tracking-widest">Kamera Tidak Diakses</span>';
                    detectionResult.innerText = 'Izinkan Akses Kamera';
                });
            } else {
                statusIndicator.innerHTML = '<div class="w-2 h-2 rounded-full bg-rose-500"></div><span class="text-white/80 text-[10px] font-black uppercase tracking-widest">Browser Tidak Support</span>';
            }
        }

        window.addEventListener('load', init);

        video.addEventListener('play', async () => {
            const canvas = faceapi.createCanvasFromMedia(video);
            document.querySelector('.video-container').append(canvas);

            const displaySize = { width: video.offsetWidth, height: video.offsetHeight };
            faceapi.matchDimensions(canvas, displaySize);

            // Fetch registered faces
            const response = await fetch('<?= BASE_URL ?>api/get_face_list.php');
            const students = await response.json();

            const labeledDescriptors = await Promise.all(
                students.map(async student => {
                    const img = await faceapi.fetchImage(student.image);
                    const detections = await faceapi.detectSingleFace(img).withFaceLandmarks().withFaceDescriptor();
                    if (!detections) return null;
                    return new faceapi.LabeledFaceDescriptors(student.name, [detections.descriptor]);
                })
            );

            const validDescriptors = labeledDescriptors.filter(d => d !== null);
            const faceMatcher = new faceapi.FaceMatcher(validDescriptors, 0.6);

            // Detection Loop
            setInterval(async () => {
                const detections = await faceapi.detectAllFaces(video, new faceapi.TinyFaceDetectorOptions()).withFaceLandmarks().withFaceDescriptors();
                const resizedDetections = faceapi.resizeResults(detections, displaySize);

                canvas.getContext('2d').clearRect(0, 0, canvas.width, canvas.height);

                const results = resizedDetections.map(d => faceMatcher.findBestMatch(d.descriptor));

                // Clear existing labels
                document.querySelectorAll('.detection-label').forEach(l => l.remove());

                if (results.length > 0) {
                    detectionResult.innerText = `${results.length} Wajah Terdeteksi`;
                    results.forEach((result, i) => {
                        const box = resizedDetections[i].detection.box;
                        const label = result.toString();

                        // Custom label element
                        const labelDiv = document.createElement('div');
                        labelDiv.className = 'detection-label';
                        labelDiv.innerText = label.split(' (')[0];
                        labelDiv.style.left = `${box.x + box.width / 2}px`;
                        labelDiv.style.top = `${box.y}px`;
                        document.querySelector('.video-container').appendChild(labelDiv);

                        // Draw box on canvas
                        const drawBox = new faceapi.draw.DrawBox(box, { label: '' });
                        drawBox.draw(canvas);
                    });
                } else {
                    detectionResult.innerText = 'Mencari Wajah...';
                }
            }, 100);
        });

        // Handle window resize
        window.addEventListener('resize', () => {
            const canvas = document.querySelector('canvas');
            if (canvas) {
                const displaySize = { width: video.offsetWidth, height: video.offsetHeight };
                faceapi.matchDimensions(canvas, displaySize);
            }
        });
    </script>
</body>
</html>
