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
            margin: 20px;
        }
        table {
            width: 100%;
            border-collapse: collapse;
        }
        th, td {
            padding: 10px;
            border: 1px solid #ddd;
        }
        .green { color: green; }
        .red { color: red; }
        .orange { color: orange; }
    </style>
</head>
<body>
    <h1>Tabella Voti Studenti</h1>
    
    <?php if (count($studenti) > 0): ?>
        <table>
            <tr>
                <th>N°</th>
                <th>Nome</th>
                <th>Cognome</th>
                <th>Voto</th>
            </tr>
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
                    <td class="<?php echo $colore; ?>">
                        <?php echo $studente['voto']; ?>
                    </td>
                </tr>
            <?php 
                $numero++;
            endforeach; 
            ?>
        </table>
        
        <h3>Statistiche</h3>
        <p>Totale studenti: <?php echo count($studenti); ?></p>
        <p>Media voti: <?php echo number_format($totale_voti / count($studenti), 2); ?></p>
        <p>Promossi (voto >= 6): <span class="green"><?php echo $promossi; ?></span></p>
        <p>Non sufficienti (voto < 6): <span class="red"><?php echo $bocciati; ?></span></p>
        
    <?php else: ?>
        <p style="color: red;">Nessun dato valido trovato nel file!</p>
    <?php endif; ?>
    
    <p><a href="index.html">← Torna indietro</a></p>
</body>
</html>
