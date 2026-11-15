{{--
    Kamera ya kupiga picha ya uso.
    - HTTPS / localhost: kamera inafunguka ndani ya ukurasa (webcam au kamera ya simu).
    - HTTP kwenye mtandao (mf. http://10.x.x.x): kivinjari kinazuia kamera ndani ya ukurasa,
      kwa hiyo tunafungua programu ya kamera ya simu kupitia <input capture>.
    Matumizi:  NinCamera.capture().then(file => { ... })   // file ni JPEG au null
--}}
<style>
    .cam-backdrop {
        position: fixed; inset: 0; z-index: 400; display: none; align-items: center; justify-content: center; padding: 16px;
        background: rgba(15,23,42,.7); backdrop-filter: blur(3px);
    }
    .cam-backdrop.open { display: flex; }
    .cam {
        width: 100%; max-width: 520px; background: #0f172a; border-radius: 22px; overflow: hidden;
        box-shadow: 0 30px 60px -20px rgba(0,0,0,.6); color: #fff; font-family: inherit;
    }
    .cam-head { display: flex; align-items: center; gap: 10px; padding: 14px 18px; }
    .cam-head b { font-size: 15px; }
    .cam-head small { display: block; font-size: 12px; color: #94a3b8; }
    .cam-x { margin-left: auto; width: 34px; height: 34px; border-radius: 10px; border: 1px solid #334155; background: transparent; color: #e2e8f0; cursor: pointer; font-size: 15px; }
    .cam-view { position: relative; aspect-ratio: 3 / 4; max-height: 62vh; margin: 0 auto; background: #020617; overflow: hidden; }
    .cam-view video, .cam-view canvas { position: absolute; inset: 0; width: 100%; height: 100%; object-fit: cover; }
    .cam-view video.mirror { transform: scaleX(-1); }
    .cam-view canvas { display: none; }
    .cam.shot .cam-view canvas { display: block; }
    .cam.shot .cam-view video { visibility: hidden; }
    /* Mwongozo wa kuweka uso katikati */
    .cam-guide {
        position: absolute; left: 50%; top: 46%; width: 58%; height: 62%; transform: translate(-50%, -50%);
        border: 2px dashed rgba(255,255,255,.55); border-radius: 50% / 45%; pointer-events: none;
        box-shadow: 0 0 0 999px rgba(2,6,23,.35);
    }
    .cam.shot .cam-guide { display: none; }
    .cam-msg { position: absolute; inset: 0; display: grid; place-items: center; text-align: center; padding: 24px; font-size: 14px; color: #cbd5e1; }
    .cam-msg i { display: block; font-size: 34px; margin-bottom: 8px; color: #64748b; }
    .cam-bar { display: flex; align-items: center; justify-content: center; gap: 14px; padding: 16px; }
    .cam-btn {
        height: 44px; padding: 0 18px; border-radius: 12px; border: 1px solid #334155; background: #1e293b; color: #e2e8f0;
        font: inherit; font-size: 14px; font-weight: 700; cursor: pointer; display: inline-flex; align-items: center; gap: 7px;
    }
    .cam-btn.primary { background: #16a34a; border-color: #16a34a; color: #fff; }
    .cam-shutter {
        width: 66px; height: 66px; border-radius: 50%; border: 4px solid #fff; background: #16a34a; cursor: pointer;
        box-shadow: 0 0 0 4px rgba(22,163,74,.35); transition: transform .1s;
    }
    .cam-shutter:active { transform: scale(.93); }
    .cam-shutter:disabled { opacity: .4; cursor: not-allowed; }
    .cam .when-shot { display: none; }
    .cam.shot .when-shot { display: inline-flex; }
    .cam.shot .when-live { display: none; }
</style>

<div class="cam-backdrop no-print" id="ninCamera" aria-hidden="true">
    <div class="cam" role="dialog" aria-modal="true" aria-label="Take photo">
        <div class="cam-head">
            <i class="bi bi-camera" style="font-size:20px;color:#4ade80"></i>
            <div><b>Take photo</b><small>Face the camera, good light, plain background</small></div>
            <button type="button" class="cam-x" data-cam-close title="Close"><i class="bi bi-x-lg"></i></button>
        </div>
        <div class="cam-view">
            <video playsinline muted autoplay></video>
            <canvas></canvas>
            <div class="cam-guide"></div>
            <div class="cam-msg" hidden></div>
        </div>
        <div class="cam-bar">
            <button type="button" class="cam-btn when-live" data-cam-switch title="Switch camera"><i class="bi bi-arrow-repeat"></i></button>
            <button type="button" class="cam-shutter when-live" data-cam-snap title="Capture" disabled></button>
            <button type="button" class="cam-btn when-live" data-cam-close>Cancel</button>
            <button type="button" class="cam-btn when-shot" data-cam-retake><i class="bi bi-arrow-counterclockwise"></i> Retake</button>
            <button type="button" class="cam-btn primary when-shot" data-cam-use><i class="bi bi-check2"></i> Use photo</button>
        </div>
    </div>
</div>
<input type="file" id="ninCameraFallback" accept="image/*" capture="user" hidden>

<script>
window.NinCamera = (function () {
    const modal = document.getElementById('ninCamera');
    const box = modal.querySelector('.cam');
    const video = modal.querySelector('video');
    const canvas = modal.querySelector('canvas');
    const msg = modal.querySelector('.cam-msg');
    const snap = modal.querySelector('[data-cam-snap]');
    const fallback = document.getElementById('ninCameraFallback');
    let stream = null, facing = 'user', resolver = null;

    const liveSupported = () => window.isSecureContext && navigator.mediaDevices && navigator.mediaDevices.getUserMedia;

    function stop() {
        if (stream) stream.getTracks().forEach((t) => t.stop());
        stream = null;
    }

    function finish(file) {
        stop();
        modal.classList.remove('open');
        modal.setAttribute('aria-hidden', 'true');
        box.classList.remove('shot');
        if (resolver) { resolver(file); resolver = null; }
    }

    function showMsg(html) {
        msg.innerHTML = html;
        msg.hidden = !html;
    }

    async function start() {
        stop();
        snap.disabled = true;
        showMsg('<div><i class="bi bi-camera-video"></i>Starting camera…</div>');
        try {
            stream = await navigator.mediaDevices.getUserMedia({
                video: { facingMode: facing, width: { ideal: 1280 }, height: { ideal: 1280 } },
                audio: false,
            });
            video.srcObject = stream;
            video.classList.toggle('mirror', facing === 'user');
            await video.play();
            showMsg('');
            snap.disabled = false;
        } catch (e) {
            const denied = e && (e.name === 'NotAllowedError' || e.name === 'SecurityError');
            showMsg('<div><i class="bi bi-camera-video-off"></i>' +
                (denied ? 'Camera permission was denied. Allow camera access in the browser and try again.'
                        : 'No camera was found on this device.') + '</div>');
        }
    }

    // Kata picha ya mraba kutoka katikati (kama picha ya pasipoti) kisha hifadhi kama JPEG.
    function grab() {
        const w = video.videoWidth, h = video.videoHeight;
        const side = Math.min(w, h, 1000);
        const src = Math.min(w, h);
        canvas.width = side;
        canvas.height = side;
        const ctx = canvas.getContext('2d');
        ctx.save();
        if (facing === 'user') { ctx.translate(side, 0); ctx.scale(-1, 1); }
        ctx.drawImage(video, (w - src) / 2, (h - src) / 2, src, src, 0, 0, side, side);
        ctx.restore();
        box.classList.add('shot');
    }

    snap.addEventListener('click', grab);
    modal.querySelector('[data-cam-retake]').addEventListener('click', () => box.classList.remove('shot'));
    modal.querySelector('[data-cam-switch]').addEventListener('click', () => { facing = facing === 'user' ? 'environment' : 'user'; start(); });
    modal.querySelector('[data-cam-use]').addEventListener('click', () => {
        canvas.toBlob((blob) => finish(blob ? new File([blob], 'camera-' + Date.now() + '.jpg', { type: 'image/jpeg' }) : null), 'image/jpeg', 0.9);
    });
    modal.querySelectorAll('[data-cam-close]').forEach((b) => b.addEventListener('click', () => finish(null)));
    document.addEventListener('keydown', (e) => { if (e.key === 'Escape' && modal.classList.contains('open')) finish(null); });

    // Picha kutoka kwa programu ya kamera ya simu: punguza ukubwa (simu hutoa picha za MB nyingi).
    function shrink(file, maxSide = 1200) {
        return new Promise((resolve) => {
            const img = new Image();
            img.onload = () => {
                const scale = Math.min(1, maxSide / Math.max(img.naturalWidth, img.naturalHeight));
                const c = document.createElement('canvas');
                c.width = Math.round(img.naturalWidth * scale);
                c.height = Math.round(img.naturalHeight * scale);
                c.getContext('2d').drawImage(img, 0, 0, c.width, c.height);
                URL.revokeObjectURL(img.src);
                c.toBlob((b) => resolve(b ? new File([b], 'camera-' + Date.now() + '.jpg', { type: 'image/jpeg' }) : file), 'image/jpeg', 0.88);
            };
            img.onerror = () => resolve(file);
            img.src = URL.createObjectURL(file);
        });
    }

    fallback.addEventListener('change', async function () {
        const file = this.files[0];
        this.value = '';
        if (resolver) { const r = resolver; resolver = null; r(file ? await shrink(file) : null); }
    });

    return {
        shrink,
        capture() {
            return new Promise((resolve) => {
                resolver = resolve;
                if (!liveSupported()) {
                    fallback.click();       // simu: fungua programu ya kamera
                    return;
                }
                box.classList.remove('shot');
                modal.classList.add('open');
                modal.setAttribute('aria-hidden', 'false');
                start();
            });
        },
    };
})();
</script>
