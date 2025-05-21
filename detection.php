<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1.0"/>
  <title>Oil Detection</title>

  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet"/>
  <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css" rel="stylesheet"/>

  <style>
    body {
      background-color: #fdfaf5;
    }
    .card {
      border-radius: 1rem;
      box-shadow: 0 0 15px rgba(0, 0, 0, 0.1);
    }
    .status-badge {
      font-size: 1.1rem;
      padding: 8px 12px;
    }
    #camera-stream {
      width: 100%;
      height: 250px;
      background-color: #ddd;
    }
    .progress-bar {
      height: 30px;
      border-radius: 10px;
    }
    .progress {
      height: 30px;
    }
    #loading {
      display: none;
      font-weight: bold;
      font-size: 18px;
      color: #007bff;
    }
  </style>
</head>
<body>
  <div class="container py-5">
    <h2 class="mb-4 text-center">Oil Detection Dashboard</h2>

    <div class="row g-4">
      <!-- Oil Status Card -->
      <div class="col-md-4">
        <div class="card p-4 text-center">
          <h5>Current Status</h5>
          <i class="fas fa-tint fa-bounce fa-3x my-3 text-warning"></i>
          <h4 id="oil-status">
            <span class="badge bg-secondary status-badge">
              <i class="fas fa-question-circle me-2"></i>Waiting
            </span>
          </h4>
          <p id="status-note" class="text-muted small">Not yet detected</p>
          <p class="text-muted small mt-2" id="last-updated">Last updated: --</p>

          <h5 class="my-3">Oil Cleanliness</h5>
          <div class="progress">
            <div id="clean-progress" class="progress-bar bg-secondary" role="progressbar" style="width: 0%">0%</div>
          </div>
        </div>
      </div>

      <!-- Camera & AI Section -->
      <div class="col-md-8">
        <div class="card p-4 text-center">
          <h5>Oil Sample Inspection</h5>
          <div id="camera-stream"></div>
          <div class="mt-3">
            <button class="btn btn-primary" id="start-camera">
              <i class="fas fa-camera me-2"></i>Start Detection
            </button>
            <div id="loading" class="mt-2">Detecting... Please wait 10 seconds</div>
          </div>
        </div>

        <!-- AI Detection Result -->
        <div class="card p-4 text-center mt-4">
          <h5>AI-Based Oil Detection</h5>
          <div id="label-container" class="mb-2"></div>
          <div id="final-result" style="margin-top: 20px; font-weight: bold;"></div>
        </div>
      </div>

      <!-- Bottom Buttons -->
      <div class="col-12 text-center mt-4">
        <button class="btn btn-warning px-4 py-2 rounded-pill" id="recheck-button">
          <i class="fas fa-sync-alt me-2"></i>Recheck
        </button>
        <button class="btn btn-outline-danger px-4 py-2 rounded-pill" onclick="window.location.href='dashboard.php'">
          <i class="fas fa-sign-out-alt me-2"></i>Exit to Dashboard
        </button>
      </div>
    </div>
  </div>

  <!-- TensorFlow + Teachable Machine -->
  <script src="https://cdn.jsdelivr.net/npm/@tensorflow/tfjs@latest/dist/tf.min.js"></script>
  <script src="https://cdn.jsdelivr.net/npm/@teachablemachine/image@latest/dist/teachablemachine-image.min.js"></script>

  <script>
    const URL = "https://teachablemachine.withgoogle.com/models/lxFOHSWfS/";
    let model, webcam, labelContainer, maxPredictions;
    let predictionsCount = {}, lastPrediction = [], isRunning = false;

    const cameraStreamContainer = document.getElementById('camera-stream');
    const startCameraButton = document.getElementById('start-camera');
    const recheckButton = document.getElementById('recheck-button');
    const finalResult = document.getElementById('final-result');
    const loading = document.getElementById('loading');

    startCameraButton.addEventListener('click', () => {
      startDetection();
    });

    recheckButton.addEventListener('click', () => {
      startDetection();
    });

    async function startDetection() {
      loading.style.display = "block";
      startCameraButton.disabled = true;
      finalResult.innerHTML = "";
      await init();
      isRunning = true;
      window.requestAnimationFrame(loop);

      // ✅ 倒计时 10 秒
      let countdown = 10;
      loading.textContent = `Detecting... Please wait ${countdown} seconds`;
      const countdownInterval = setInterval(() => {
        countdown--;
        if (countdown > 0) {
          loading.textContent = `Detecting... Please wait ${countdown} seconds`;
        } else {
          clearInterval(countdownInterval);
        }
      }, 1000);

      setTimeout(stopDetectionAndShowResult, 10000);
    }

    async function init() {
      const modelURL = URL + "model.json";
      const metadataURL = URL + "metadata.json";

      model = await tmImage.load(modelURL, metadataURL);
      maxPredictions = model.getTotalClasses();

      webcam = new tmImage.Webcam(300, 250, true);
      await webcam.setup();
      await webcam.play();

      cameraStreamContainer.innerHTML = '';
      cameraStreamContainer.appendChild(webcam.canvas);

      labelContainer = document.getElementById("label-container");
      labelContainer.innerHTML = '';
      document.getElementById("final-result").innerHTML = '';

      predictionsCount = {};
      model.getClassLabels().forEach(name => predictionsCount[name] = 0);

      for (let i = 0; i < maxPredictions; i++) {
        labelContainer.appendChild(document.createElement("div"));
      }
    }

    async function loop() {
      if (!isRunning) return;
      webcam.update();
      await predict();
      window.requestAnimationFrame(loop);
    }

    async function predict() {
      const prediction = await model.predict(webcam.canvas);
      lastPrediction = prediction;

      for (let i = 0; i < maxPredictions; i++) {
        const className = prediction[i].className;
        const prob = prediction[i].probability;
        labelContainer.childNodes[i].innerHTML = `${className}: ${(prob * 100).toFixed(2)}%`;

        if (prob > 0.8) {
          predictionsCount[className]++;
        }
      }
    }

    async function stopDetectionAndShowResult() {
      isRunning = false;
      await webcam.stop();
      loading.style.display = "none";

      let topClass = null;
      let topCount = -1;
      for (let cls in predictionsCount) {
        if (predictionsCount[cls] > topCount) {
          topClass = cls;
          topCount = predictionsCount[cls];
        }
      }

      const oilStatus = document.getElementById("oil-status");
      const statusNote = document.getElementById("status-note");
      const cleanProgress = document.getElementById("clean-progress");
      const lastUpdated = document.getElementById("last-updated");

      const now = new Date();
      lastUpdated.textContent = "Last updated: " + now.toLocaleTimeString();

      let cleanProb = 0;
      lastPrediction.forEach(pred => {
        if (pred.className.toLowerCase().includes("clean")) {
          cleanProb = pred.probability;
        }
      });
      const percent = Math.round(cleanProb * 100);

      let statusText = "Unknown", badgeClass = "bg-secondary", noteText = "Unable to determine", iconClass = "fas fa-question-circle";
      if (percent > 70) {
        statusText = "Clean";
        badgeClass = "bg-success";
        iconClass = "fas fa-check-circle";
        noteText = "Oil is in good condition";
      } else if (percent > 40) {
        statusText = "Moderate";
        badgeClass = "bg-warning";
        iconClass = "fas fa-exclamation-circle";
        noteText = "Oil may need checking";
      } else if (percent > 0) {
        statusText = "Dirty";
        badgeClass = "bg-danger";
        iconClass = "fas fa-exclamation-triangle";
        noteText = "Oil needs replacement";
      }

      oilStatus.innerHTML = `<span class="badge ${badgeClass} status-badge"><i class="${iconClass} me-2"></i>${statusText}</span>`;
      statusNote.textContent = noteText;
      cleanProgress.style.width = `${percent}%`;
      cleanProgress.className = `progress-bar ${badgeClass}`;
      cleanProgress.textContent = `${percent}%`;

      finalResult.innerHTML = `<span style="color: darkblue;">Most likely: ${topClass}</span> (detected ${topCount} times)`;
      startCameraButton.disabled = false;
    }
  </script>
</body>
</html>
