<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Event Image Upload</title>
    <meta name="viewport" content="width=device-width, initial-scale=1">

    <style>
        body {
            font-family: system-ui, -apple-system, BlinkMacSystemFont, sans-serif;
            background: #f5f5f5;
            margin: 0;
            padding: 0;
        }

        .container {
            max-width: 400px;
            margin: 80px auto;
            padding: 24px;
            background: white;
            border-radius: 8px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
            text-align: center;
        }

        h1 {
            margin-bottom: 20px;
            font-size: 1.4rem;
        }
        
        form {
            margin-bottom: 32px;
        }
        
        input[type="text"] {
            width: 100%;
            padding: 12px;
            font-size: 1rem;
            margin-bottom: 12px;
            text-transform: uppercase;
            max-width: 376px;
        }

        input[type="file"] {
            width: 100%;
            margin-bottom: 16px;
            font-size: 1rem;
        }

        button {
            width: 100%;
            padding: 14px;
            font-size: 1rem;
            cursor: pointer;
            border: none;
            border-radius: 6px;
            background: #0066cc;
            color: white;
        }

        button:active {
            background: #004c99;
        }
        
        .admin-link {
            display: block;
            margin-top: 16px;
            text-decoration: none;
            color: #0066cc;
        }
            .file-status {
      margin-top: 10px;
    }

    .file {
      padding: 6px;
      margin-bottom: 5px;
      border-radius: 4px;
    }

    .success {
      background-color: #e6ffed;
      color: #0a6b2d;
    }

    .error {
      background-color: #ffe6e6;
      color: #8a0000;
    }

    button.retry {
      margin-left: 10px;
    }
    .progress-container {
      margin-top: 5px;
    }

    progress {
      width: 100%;
      height: 14px;
    }
    </style>
</head>
<body>
<div class="container">

<?php
include "code_generator.php";
include "mysqlinfo.php";

date_default_timezone_set('America/Phoenix');

$code = strtoupper(trim($_POST['code']));
$codelength = strlen($code);

if ($codelength == 8)
{
    try
    {
        $currentDateTime = date("Y-m-d H:i:s");
        
        $conn = new PDO(
            "mysql:host=$host;dbname=$dbname;charset=utf8mb4",
            "$dbusername",
            "$dbpassword",
            [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]
        );
        
        $stmt = $conn->prepare("SELECT id, name FROM events WHERE start_time <= \"" . $currentDateTime . "\" AND end_time >= \"". $currentDateTime . "\" AND code = \"" . $code . "\"");
        $stmt->execute();

        $result = $stmt->fetchAll();
        
        if(count($result) > 0)
        {
            $eventID = $result[0]["id"];
            print "<h1>Upload Tree Photos</h1>
    <p>Please select images from your device to upload.</p>
        <input 
            type=\"file\" 
            name=\"images\" 
            id=\"photoInput\"
            required multiple
        />
        <input type=\"hidden\" name=\"code\" id=\"EventCodeID\" value=\"" . $code . "\" />
        <input type=\"hidden\" name=\"id\" id=\"EventID\" value=\"" . $eventID . "\" />
        <button type=\"submit\" id=\"uploadBtn\">Upload Images</button>
        <div id=\"status\" class=\"file-status\"></div>";
        }
        else
        {
            print "
    <h1>Upload Tree Photos</h1>
    <p>Invalid event code, code may be mistyped, the event may not have not started, or it may have ended.</p>
    <p>Please try again.</p>

    <!-- Participant path -->
    <form action=\"select-images.php\" method=\"POST\">
        <input
            type=\"text\"
            name=\"code\"
            placeholder=\"Enter Event Code\"
            required
        >
        <button type=\"submit\">Continue</button>
    </form>

    <!-- Admin path -->
    <a class=\"admin-link\" href=\"admin/login.html\">
        Admin Login
    </a>
    
    <p>This site does not use cookies. Some functions, such as logging in, may require manual intervention or may need to be repeated regularly.</p>
";
        }
        
    }
    catch(PDOException $e) {
        echo "<p>MySql Connection failed: " . $e->getMessage() . "</p>";
    }

}
else
{
    print "
    <h1>Upload Tree Photos</h1>
    <p>You typed in a " . $codelength . " character long code.</p>
    <p>Event codes are 8 characters long. Please verify your code is correct.</p>
    <!-- Participant path -->
    <form action=\"select-images.php\" method=\"POST\">
        <input
            type=\"text\"
            name=\"code\"
            placeholder=\"Enter Event Code\"
            required
        >
        <button type=\"submit\">Continue</button>
    </form>

    <!-- Admin path -->
    <a class=\"admin-link\" href=\"admin/login.html\">
        Admin Login
    </a>
    
    <p>This site does not use cookies. Some functions, such as logging in, may require manual intervention or may need to be repeated regularly.</p>
";
}

    
    
?>
</div>

<script>
const uploadBtn = document.getElementById('uploadBtn');
const photoInput = document.getElementById('photoInput');
const statusDiv = document.getElementById('status');

let pendingUploads = 0;
let successfulUploads = 0;
let totalUploads = 0;

uploadBtn.addEventListener('click', () => {
  const files = photoInput.files;
  statusDiv.innerHTML = '';

  if (!files.length) {
    alert('Please select at least one photo.');
    return;
  }

  totalUploads = files.length;
  pendingUploads = files.length;
  successfulUploads = 0;

  [...files].forEach(file => uploadFile(file));
});

function maybeResetFileInput() {
  photoInput.value = '';
}

function uploadFile(file) {
  const formData = new FormData();
  eventCodeValue = document.getElementById("EventCodeID").value;
  eventIDValue = document.getElementById("EventID").value;
  formData.append('images', file);
  formData.append('code', eventCodeValue);
  formData.append('id', eventIDValue);

  const statusEl = document.createElement('div');
  statusEl.className = 'file';
  statusEl.textContent = file.name;

  const progressContainer = document.createElement('div');
  progressContainer.className = 'progress-container';

  const progressBar = document.createElement('progress');
  progressBar.max = 100;
  progressBar.value = 0;

  progressContainer.appendChild(progressBar);
  statusEl.appendChild(progressContainer);
  statusDiv.appendChild(statusEl);

  const xhr = new XMLHttpRequest();
  xhr.open('POST', 'upload-ajax.php', true);

  xhr.upload.onprogress = (event) => {
    if (event.lengthComputable) {
      const percent = Math.round((event.loaded / event.total) * 100);
      progressBar.value = percent;
    }
  };

  xhr.onload = () => {
    pendingUploads--;

    if (xhr.status === 200) {
      try {
        const result = JSON.parse(xhr.responseText);

        if (result.success) {
          successfulUploads++;
          progressBar.value = 100;
          statusEl.classList.add('success');
          progressContainer.remove();
          statusEl.textContent = `${file.name} uploaded successfully`;
        } else {
          showError(statusEl, file, result.error);
        }
      } catch {
        showError(statusEl, file, 'Invalid server response');
      }
    } else {
      showError(statusEl, file, 'Server error');
    }

    maybeResetFileInput();
  };

  xhr.onerror = () => {
    pendingUploads--;
    showError(statusEl, file, 'Network error');
    maybeResetFileInput();
  };

  xhr.send(formData);
}

function showError(statusEl, file, message) {
  statusEl.classList.add('error');
  statusEl.textContent = `${file.name} failed: ${message}`;

  const retryBtn = document.createElement('button');
  retryBtn.textContent = 'Retry';
  retryBtn.className = 'retry';
  retryBtn.onclick = () => {
    statusEl.remove();
    uploadFile(file);
  };

  statusEl.appendChild(retryBtn);
}
</script>

</body>
</html>


