<?php
// Estrutura comum das páginas: pagina_inicio() abre o HTML + menu, pagina_fim() fecha.
function pagina_inicio(string $titulo, string $extra_head = ''): void {
    echo '<!DOCTYPE html><html lang="pt"><head><meta charset="utf-8">'
        .'<meta name="viewport" content="width=device-width,initial-scale=1">'
        .'<title>'.e($titulo).' - SGB</title>'
        .'<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">'
        .'<link href="'.BASE_URL.'assets/css/style.css" rel="stylesheet">'
        .$extra_head
        .'</head><body style="background:#f5f7fb">';
    include __DIR__.'/header.php';
    echo '<div class="container my-4">';
    flash_mostrar();
}
function pagina_fim(): void {
    echo '</div>';
    include __DIR__.'/footer.php';
    echo '</body></html>';
}
?>
