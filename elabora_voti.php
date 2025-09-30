<?php
// Script PHP per elaborare i voti degli studenti

// Verifica che il file sia stato caricato
if (!isset($_FILES['file_voti'])) {
    die("Errore: Nessun file ricevuto!");
}

$file = $_FILES['file_voti'];

// Controllo errori nel caricamento
if ($file['error'] !== UPLOAD_ERR_OK) {
    die("Errore nel caricamento del file!");
}

// Legge il contenuto del file
$contenuto = file_get_contents($file['tmp_name']);
$righe = explode("\n", $contenuto);

// Array per memorizzare gli studenti
$studenti = array();

// Elabora ogni riga del file
foreach ($righe as $riga) {
    // Rimuove spazi bianchi
    $riga = trim($riga);
    
    // Salta righe vuote
    if (empty($riga)) {
        continue;
    }
    
    // Divide la riga in nome, cognome e voto
    $dati = explode(",", $riga);
    
    // Verifica che ci siano 3 elementi
    if (count($dati) === 3) {
        $studenti[] = array(
            'nome' => trim($dati[0]),
            'cognome' => trim($dati[1]),
            'voto' => intval(trim($dati[2]))
        );
    }
}

// Funzione per determinare il colore in base al voto
function getColoreVoto($voto) {
    if ($voto >= 6) {
        return 'green';
    } else if ($voto < 5) {
        return 'red';
    } else {
        return 'orange'; // voto = 5
    }
}
?>

<!DOCTYPE html>
<html lang="it">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Risultati Voti</title>
    <style>
        body {
            font-family: Arial, sans-serif;
            max-width: 800px;
            margin: 50px auto;
            padding: 20px;
        }
        h1 {
            text-align: center;
            color: #333;
        }
        table {
            width: 100%;
            border-collapse: collapse;
            margin-top: 20px;
        }
        th {
            background-color: #4CAF50;
            color: white;
            padding: 12px;
            text-align: left;
        }
        td {
            padding: 10px;
            border-bottom: 1px solid #ddd;
        }
        tr:hover {
            background-color: #f5f5f5;
        }
        .voto {
            font-weight: bold;
            font-size: 18px;
            text-align: center;
        }
        .green {
            color: green;
        }
        .red {
            color: red;
        }
        .orange {
            color: orange;
        }
        .back-button {
            display: inline-block;
            margin-top: 20px;
            padding: 10px 20px;
            background-color: #4CAF50;
            color: white;
            text-decoration: none;
            border-radius: 4px;
        }
        .back-button:hover {
            background-color: #45a049;
        }
        .stats {
            margin-top: 30px;
            padding: 15px;
            background-color: #f0f0f0;
            border-radius: 4px;
        }
    </style>
</head>
<body>
    <h1>Tabella Voti Studenti</h1>
    
    <?php if (count($studenti) > 0): ?>
        <table>
            <thead>
                <tr>
                    <th>N°</th>
                    <th>Nome</th>
                    <th>Cognome</th>
                    <th>Voto</th>
                </tr>
            </thead>
            <tbody>
                <?php 
                $numero = 1;
                $totale_voti = 0;
                $promossi = 0;
                $bocciati = 0;
                
                foreach ($studenti as $studente): 
                    $colore = getColoreVoto($studente['voto']);
                    $totale_voti += $studente['voto'];
                    
                    if ($studente['voto'] >= 6) {
                        $promossi++;
                    } else {
                        $bocciati++;
                    }
                ?>
                    <tr>
                        <td><?php echo $numero; ?></td>
                        <td><?php echo htmlspecialchars($studente['nome']); ?></td>
                        <td><?php echo htmlspecialchars($studente['cognome']); ?></td>
                        <td class="voto <?php echo $colore; ?>">
                            <?php echo $studente['voto']; ?>
                        </td>
                    </tr>
                <?php 
                    $numero++;
                endforeach; 
                ?>
            </tbody>
        </table>
        
        <div class="stats">
            <h3>Statistiche</h3>
            <p><strong>Totale studenti:</strong> <?php echo count($studenti); ?></p>
            <p><strong>Media voti:</strong> <?php echo number_format($totale_voti / count($studenti), 2); ?></p>
            <p><strong>Promossi (voto >= 6):</strong> <span class="green"><?php echo $promossi; ?></span></p>
            <p><strong>Non sufficienti (voto < 6):</strong> <span class="red"><?php echo $bocciati; ?></span></p>
        </div>
        
    <?php else: ?>
        <p style="color: red; text-align: center;">Nessun dato valido trovato nel file!</p>
    <?php endif; ?>
    
    <a href="index.html" class="back-button">← Torna indietro</a>
</body>
</html>
