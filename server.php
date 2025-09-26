<?php
header('Content-Type: text/html; charset=utf-8');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST');
header('Access-Control-Allow-Headers: Content-Type');

// Gestisci richieste OPTIONS per CORS
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit();
}

// Verifica che sia una richiesta POST
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    displayError('Metodo non consentito. Utilizzare POST.');
    exit();
}

try {
    // Verifica che sia stato caricato un file
    if (!isset($_FILES['file']) || $_FILES['file']['error'] !== UPLOAD_ERR_OK) {
        throw new Exception('Errore nel caricamento del file.');
    }

    $file = $_FILES['file'];
    
    // Verifica la dimensione del file (max 5MB)
    if ($file['size'] > 5 * 1024 * 1024) {
        throw new Exception('Il file è troppo grande. Dimensione massima consentita: 5MB.');
    }
    
    // Verifica che sia un file di testo
    $allowedTypes = ['text/plain', 'text/csv', 'application/csv'];
    $finfo = finfo_open(FILEINFO_MIME_TYPE);
    $mimeType = finfo_file($finfo, $file['tmp_name']);
    finfo_close($finfo);
    
    if (!in_array($mimeType, $allowedTypes)) {
        throw new Exception('Tipo di file non supportato. Utilizzare file .txt o .csv.');
    }

    // Leggi il contenuto del file
    $content = file_get_contents($file['tmp_name']);
    
    if ($content === false) {
        throw new Exception('Impossibile leggere il contenuto del file.');
    }
    
    // Converti in UTF-8 se necessario
    if (!mb_check_encoding($content, 'UTF-8')) {
        $content = mb_convert_encoding($content, 'UTF-8', 'auto');
    }
    
    // Processa il contenuto
    $students = processStudentData($content);
    
    // Mostra i risultati
    displayResults($students, $file['name']);

} catch (Exception $e) {
    http_response_code(400);
    displayError($e->getMessage());
}

/**
 * Processa i dati degli studenti dal contenuto del file
 * 
 * @param string $content Contenuto del file
 * @return array Array di studenti
 */
function processStudentData($content) {
    $students = [];
    $lines = explode("\n", $content);
    $lineNumber = 0;
    $errors = [];
    
    foreach ($lines as $line) {
        $lineNumber++;
        $line = trim($line);
        
        // Salta le righe vuote
        if (empty($line)) {
            continue;
        }
        
        // Dividi la riga per virgola
        $parts = array_map('trim', explode(',', $line));
        
        // Verifica che ci siano esattamente 5 campi
        if (count($parts) !== 5) {
            $errors[] = "Riga $lineNumber: numero di campi errato (" . count($parts) . " invece di 5)";
            continue;
        }
        
        list($cognome, $nome, $classe, $scuola, $dataNascita) = $parts;
        
        // Validazione dei dati
        $validationErrors = validateStudent($cognome, $nome, $classe, $scuola, $dataNascita, $lineNumber);
        
        if (!empty($validationErrors)) {
            $errors = array_merge($errors, $validationErrors);
            continue;
        }
        
        // Aggiungi lo studente all'array
        $students[] = [
            'cognome' => sanitizeString($cognome),
            'nome' => sanitizeString($nome),
            'classe' => sanitizeString($classe),
            'scuola' => sanitizeString($scuola),
            'data_nascita' => $dataNascita
        ];
    }
    
    // Se ci sono troppi errori, lancia un'eccezione
    if (count($errors) > 0 && count($students) === 0) {
        throw new Exception("Errori di validazione:\n" . implode("\n", array_slice($errors, 0, 10)));
    }
    
    // Se ci sono alcuni errori ma anche dati validi, logga gli errori
    if (count($errors) > 0) {
        error_log("Errori nella processazione del file studenti: " . implode("; ", $errors));
    }
    
    return $students;
}

/**
 * Valida i dati di un singolo studente
 * 
 * @param string $cognome
 * @param string $nome
 * @param string $classe
 * @param string $scuola
 * @param string $dataNascita
 * @param int $lineNumber
 * @return array Array di errori (vuoto se tutto è valido)
 */
function validateStudent($cognome, $nome, $classe, $scuola, $dataNascita, $lineNumber) {
    $errors = [];
    
    // Verifica che i campi non siano vuoti
    if (empty($cognome)) {
        $errors[] = "Riga $lineNumber: cognome mancante";
    }
    
    if (empty($nome)) {
        $errors[] = "Riga $lineNumber: nome mancante";
    }
    
    if (empty($classe)) {
        $errors[] = "Riga $lineNumber: classe mancante";
    }
    
    if (empty($scuola)) {
        $errors[] = "Riga $lineNumber: scuola mancante";
    }
    
    if (empty($dataNascita)) {
        $errors[] = "Riga $lineNumber: data di nascita mancante";
    }
    
    // Verifica lunghezza dei campi
    if (strlen($cognome) > 50) {
        $errors[] = "Riga $lineNumber: cognome troppo lungo (max 50 caratteri)";
    }
    
    if (strlen($nome) > 50) {
        $errors[] = "Riga $lineNumber: nome troppo lungo (max 50 caratteri)";
    }
    
    if (strlen($classe) > 20) {
        $errors[] = "Riga $lineNumber: classe troppo lunga (max 20 caratteri)";
    }
    
    if (strlen($scuola) > 100) {
        $errors[] = "Riga $lineNumber: scuola troppo lunga (max 100 caratteri)";
    }
    
    // Validazione della data di nascita
    if (!empty($dataNascita) && !validateDate($dataNascita)) {
        $errors[] = "Riga $lineNumber: formato data non valido (usa gg/mm/aaaa o aaaa-mm-gg)";
    }
    
    return $errors;
}

/**
 * Valida e normalizza una data
 * 
 * @param string $date
 * @return bool
 */
function validateDate($date) {
    // Prova diversi formati di data
    $formats = [
        'd/m/Y',    // gg/mm/aaaa
        'm/d/Y',    // mm/gg/aaaa
        'Y-m-d',    // aaaa-mm-gg
        'd-m-Y',    // gg-mm-aaaa
        'Y/m/d'     // aaaa/mm/gg
    ];
    
    foreach ($formats as $format) {
        $dateObj = DateTime::createFromFormat($format, $date);
        if ($dateObj && $dateObj->format($format) === $date) {
            // Verifica che la data sia ragionevole (tra 1900 e oggi + 20 anni)
            $year = $dateObj->format('Y');
            if ($year >= 1900 && $year <= date('Y') + 20) {
                return true;
            }
        }
    }
    
    return false;
}

/**
 * Pulisce e sanitizza una stringa
 * 
 * @param string $string
 * @return string
 */
function sanitizeString($string) {
    // Rimuovi caratteri di controllo e spazi extra
    $string = preg_replace('/[\x00-\x1F\x7F]/', '', $string);
    $string = trim($string);
    
    // Converti caratteri speciali HTML
    $string = htmlspecialchars($string, ENT_QUOTES | ENT_HTML5, 'UTF-8');
    
    return $string;
}

/**
 * Mostra i risultati in formato HTML
 * 
 * @param array $students Array di studenti
 * @param string $filename Nome del file processato
 */
function displayResults($students, $filename) {
    $studentCount = count($students);
    
    echo "<!DOCTYPE html>
<html lang='it'>
<head>
    <meta charset='UTF-8'>
    <meta name='viewport' content='width=device-width, initial-scale=1.0'>
    <title>Risultati - Gestione Studenti</title>
    <style>
        body {
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            max-width: 1200px;
            margin: 0 auto;
            padding: 20px;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            min-height: 100vh;
        }

        .container {
            background: rgba(255, 255, 255, 0.95);
            border-radius: 15px;
            padding: 30px;
            box-shadow: 0 20px 40px rgba(0, 0, 0, 0.1);
            backdrop-filter: blur(10px);
        }

        h1 {
            text-align: center;
            color: #333;
            margin-bottom: 10px;
            font-size: 2.5em;
            text-shadow: 2px 2px 4px rgba(0, 0, 0, 0.1);
        }

        .success {
            background: linear-gradient(135deg, #51cf66, #40c057);
            color: white;
            padding: 15px;
            border-radius: 10px;
            margin: 20px 0;
            text-align: center;
            font-weight: bold;
        }

        .file-info {
            background: linear-gradient(135deg, #339af0, #228be6);
            color: white;
            padding: 15px;
            border-radius: 10px;
            margin: 20px 0;
            text-align: center;
        }

        table {
            width: 100%;
            border-collapse: collapse;
            margin-top: 20px;
            background: white;
            border-radius: 10px;
            overflow: hidden;
            box-shadow: 0 10px 25px rgba(0, 0, 0, 0.1);
        }

        th {
            background: linear-gradient(135deg, #667eea, #764ba2);
            color: white;
            padding: 15px;
            text-align: left;
            font-weight: bold;
            text-transform: uppercase;
            letter-spacing: 1px;
        }

        td {
            padding: 12px 15px;
            border-bottom: 1px solid #eee;
            transition: background-color 0.3s ease;
        }

        tr:hover td {
            background-color: #f8f9ff;
        }

        tr:nth-child(even) {
            background-color: #f9f9f9;
        }

        .back-button {
            background: linear-gradient(45deg, #667eea, #764ba2);
            color: white;
            padding: 12px 25px;
            border: none;
            border-radius: 25px;
            cursor: pointer;
            font-size: 16px;
            font-weight: bold;
            margin: 20px 0;
            text-decoration: none;
            display: inline-block;
            transition: all 0.3s ease;
        }

        .back-button:hover {
            transform: translateY(-2px);
            box-shadow: 0 10px 20px rgba(102, 126, 234, 0.4);
        }

        .stats {
            display: flex;
            justify-content: space-around;
            margin: 20px 0;
            flex-wrap: wrap;
        }

        .stat-item {
            background: linear-gradient(135deg, #ffd43b, #fab005);
            color: white;
            padding: 15px 20px;
            border-radius: 10px;
            text-align: center;
            margin: 5px;
            flex: 1;
            min-width: 150px;
        }

        .stat-number {
            font-size: 2em;
            font-weight: bold;
            display: block;
        }

        .stat-label {
            font-size: 0.9em;
            opacity: 0.9;
        }

        @media (max-width: 768px) {
            .container {
                padding: 15px;
            }
            
            h1 {
                font-size: 2em;
            }
            
            table {
                font-size: 14px;
            }
            
            th, td {
                padding: 10px 8px;
            }
            
            .stats {
                flex-direction: column;
            }
        }
    </style>
</head>
<body>
    <div class='container'>
        <h1>📚 Risultati Elaborazione</h1>
        
        <div class='success'>
            ✅ File elaborato con successo!
        </div>
        
        <div class='file-info'>
            📄 <strong>File:</strong> " . htmlspecialchars($filename, ENT_QUOTES, 'UTF-8') . "
        </div>
        
        <div class='stats'>
            <div class='stat-item'>
                <span class='stat-number'>$studentCount</span>
                <span class='stat-label'>Studenti Trovati</span>
            </div>
        </div>";

    if ($studentCount > 0) {
        echo "<table>
            <thead>
                <tr>
                    <th>👤 Cognome</th>
                    <th>📝 Nome</th>
                    <th>🏫 Classe</th>
                    <th>🏛️ Scuola</th>
                    <th>📅 Data di Nascita</th>
                </tr>
            </thead>
            <tbody>";
        
        foreach ($students as $index => $student) {
            echo "<tr>
                <td>" . htmlspecialchars($student['cognome'], ENT_QUOTES, 'UTF-8') . "</td>
                <td>" . htmlspecialchars($student['nome'], ENT_QUOTES, 'UTF-8') . "</td>
                <td>" . htmlspecialchars($student['classe'], ENT_QUOTES, 'UTF-8') . "</td>
                <td>" . htmlspecialchars($student['scuola'], ENT_QUOTES, 'UTF-8') . "</td>
                <td>" . htmlspecialchars($student['data_nascita'], ENT_QUOTES, 'UTF-8') . "</td>
            </tr>";
        }
        
        echo "</tbody>
        </table>";
    } else {
        echo "<div style='background: linear-gradient(135deg, #ff8a65, #ff7043); color: white; padding: 20px; border-radius: 10px; text-align: center; margin: 20px 0;'>
            ⚠️ Nessun dato valido trovato nel file.
        </div>";
    }
    
    echo "<a href='javascript:history.back()' class='back-button'>
            ← Torna Indietro
        </a>
        
    </div>
</body>
</html>";
}

/**
 * Mostra un messaggio di errore in formato HTML
 * 
 * @param string $message Messaggio di errore
 */
function displayError($message) {
    echo "<!DOCTYPE html>
<html lang='it'>
<head>
    <meta charset='UTF-8'>
    <meta name='viewport' content='width=device-width, initial-scale=1.0'>
    <title>Errore - Gestione Studenti</title>
    <style>
        body {
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            max-width: 800px;
            margin: 0 auto;
            padding: 20px;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
        }

        .container {
            background: rgba(255, 255, 255, 0.95);
            border-radius: 15px;
            padding: 40px;
            box-shadow: 0 20px 40px rgba(0, 0, 0, 0.1);
            backdrop-filter: blur(10px);
            text-align: center;
        }

        h1 {
            color: #333;
            margin-bottom: 20px;
            font-size: 2.5em;
        }

        .error {
            background: linear-gradient(135deg, #ff6b6b, #ee5a6f);
            color: white;
            padding: 20px;
            border-radius: 10px;
            margin: 20px 0;
            font-weight: bold;
            font-size: 1.1em;
        }

        .back-button {
            background: linear-gradient(45deg, #667eea, #764ba2);
            color: white;
            padding: 12px 25px;
            border: none;
            border-radius: 25px;
            cursor: pointer;
            font-size: 16px;
            font-weight: bold;
            margin: 20px 0;
            text-decoration: none;
            display: inline-block;
            transition: all 0.3s ease;
        }

        .back-button:hover {
            transform: translateY(-2px);
            box-shadow: 0 10px 20px rgba(102, 126, 234, 0.4);
        }

        .error-icon {
            font-size: 4em;
            margin-bottom: 20px;
        }
    </style>
</head>
<body>
    <div class='container'>
        <div class='error-icon'>❌</div>
        <h1>Errore nell'Elaborazione</h1>
        
        <div class='error'>
            " . htmlspecialchars($message, ENT_QUOTES, 'UTF-8') . "
        </div>
        
        <a href='javascript:history.back()' class='back-button'>
            ← Torna Indietro
        </a>
    </div>
</body>
</html>";
}

/**
 * Logga un messaggio con timestamp
 * 
 * @param string $message
 */
function logMessage($message) {
    $timestamp = date('Y-m-d H:i:s');
    error_log("[$timestamp] $message");
}

// Log dell'operazione
if (isset($_FILES['file'])) {
    logMessage("Ricevuto file: " . $_FILES['file']['name'] . " (" . $_FILES['file']['size'] . " bytes)");
}
?>
