<?php
$transcriptPath = 'C:\\Users\\USER\\.gemini\\antigravity-ide\\brain\\26c2e39e-a4d2-46c0-a8c8-56d0b52f228b\\.system_generated\\logs\\transcript.jsonl';
if (!file_exists($transcriptPath)) {
    die("No existe el archivo de transcripción.\n");
}

$file = fopen($transcriptPath, 'r');
$matches = [];
$index = 0;
while (($line = fgets($file)) !== false) {
    $index++;
    if ($index >= 940) {
        break; // Detenerse antes del turno actual
    }
    
    $data = json_decode($line, true);
    if (!$data) continue;
    
    $content = $data['content'] ?? '';
    if (stripos($content, '0/4') !== false || stripos($content, '0 / 4') !== false) {
        $matches[] = "Línea $index - [" . ($data['source'] ?? $data['type']) . "]: " . substr(strip_tags($content), 0, 500) . "...\n";
    }
}
fclose($file);

echo "Encontrados " . count($matches) . " coincidencias para '0/4':\n";
foreach ($matches as $m) {
    echo $m . "\n";
}
?>
