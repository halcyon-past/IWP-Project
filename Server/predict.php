<?php
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

$servername = "localhost";
$username = "root";
$password = "";
$db = "dropout";
$port = 3333;

$conn = new mysqli($servername, $username, $password, $db, $port);

if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}

header("Access-Control-Allow-Origin: *");
header("Access-Control-Allow-Methods: POST");
header("Access-Control-Allow-Headers: Content-Type");

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $inputData = file_get_contents('php://input');
    $data = json_decode($inputData, true);

    $requiredFields = ['gender', 'caste', 'mathematics_marks', 'english_marks', 'science_marks', 'guardian', 'internet'];
    foreach ($requiredFields as $field) {
        if (!isset($data[$field]) || empty($data[$field])) {
            echo json_encode(['error' => "Field '$field' is required and cannot be empty."]);
            exit;
        }
    }

    $url = 'https://eduinsight.onrender.com/predict';
    $ch = curl_init($url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);  
    curl_setopt($ch, CURLOPT_POST, true);            
    curl_setopt($ch, CURLOPT_HTTPHEADER, array(
        'Content-Type: application/json',             
        'Content-Length: ' . strlen($inputData)      
    ));
    curl_setopt($ch, CURLOPT_POSTFIELDS, $inputData);

    $response = curl_exec($ch);
    curl_close($ch);

    if ($response === false) {
        echo json_encode(['error' => 'Request Error: ' . curl_error($ch)]);
        exit;
    }

    $responseData = json_decode($response, true);
    if (!isset($responseData['predictions'][0])) {
        echo json_encode(['error' => 'Prediction not available in the API response.']);
        exit;
    }

    $prediction = $responseData['predictions'][0];

    $gender = (int) $data['gender'][0];               
    $caste = (int) $data['caste'][0];                 
    $mathematics_marks = (float) $data['mathematics_marks'][0]; 
    $english_marks = (float) $data['english_marks'][0];
    $science_marks = (float) $data['science_marks'][0];
    $guardian = (int) $data['guardian'][0];
    $internet = (int) $data['internet'][0];

    $stmt = $conn->prepare("
        INSERT INTO predictions_log 
        (gender, caste, mathematics_marks, english_marks, science_marks, guardian, internet, prediction) 
        VALUES (?, ?, ?, ?, ?, ?, ?, ?)
    ");

    if (!$stmt) {
        echo json_encode(['error' => 'Database prepare statement error: ' . $conn->error]);
        exit;
    }

    $stmt->bind_param(
        "iidddiis",
        $gender,
        $caste,
        $mathematics_marks,
        $english_marks,
        $science_marks,
        $guardian,
        $internet,
        $prediction
    );

    if ($stmt->execute()) {
        echo json_encode(['success' => 'Data and prediction logged successfully.', 'prediction' => $prediction]);
    } else {
        echo json_encode(['error' => 'Failed to log data to the database: ' . $stmt->error]);
    }

    $stmt->close();
} else {
    echo json_encode(['error' => 'Invalid request method. Use POST.']);
}

$conn->close();
?>
