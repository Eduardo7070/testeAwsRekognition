<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>Galeria · Busca por rosto</title>
    <style>
        *{box-sizing:border-box}body{font-family:Arial,sans-serif;margin:0;background:#f5f6f8;color:#172b36}main{max-width:1100px;margin:auto;padding:32px 20px}h1{margin-bottom:8px}h2{font-size:21px}p,small{line-height:1.6;color:#526371}.forms{display:grid;grid-template-columns:1fr 1fr;gap:20px}section.panel{background:white;border:1px solid #dce2e8;padding:22px;border-radius:10px}label{display:block;margin:14px 0 8px}input[type=file]{max-width:100%;padding:12px 0}button{padding:11px 16px;background:#145bb5;color:white;border:0;border-radius:6px;cursor:pointer;margin:6px 5px 6px 0}button.secondary{background:#e6edf6;color:#15457b}button:disabled{opacity:.55;cursor:wait}video,#preview{width:100%;max-height:280px;object-fit:contain;background:#172b36;border-radius:8px}#preview{margin-top:10px}.message{padding:12px;background:#e7effa;border-radius:6px;white-space:pre-line}.error{background:#ffe9e5;color:#7a2b20}.grid{display:grid;grid-template-columns:repeat(auto-fill,minmax(210px,1fr));gap:16px}.photo{background:white;border:1px solid #dce2e8;border-radius:8px;overflow:hidden;margin:0}.photo img{width:100%;height:180px;object-fit:cover}.caption{padding:12px;overflow-wrap:anywhere}.caption p{font-size:14px;margin:8px 0}.score{color:#10623d;font-weight:bold}#gallery-section{margin-top:30px}[hidden]{display:none!important}button:focus-visible,input:focus-visible{outline:3px solid #d49d23;outline-offset:3px}@media(max-width:700px){.forms{grid-template-columns:1fr}}
</style>
<script src="{{ asset('gallery.js') }}" defer></script>
</head>
<body><main>
    <h1>Galeria com busca por rosto</h1><p>Cadastre as fotos e encontre aquelas em que você aparece.</p>
    <noscript><p class="message error">Ative o JavaScript para enviar fotos e utilizar a câmera.</p></noscript>
    <div class="forms">
        <section class="panel"><h2>1. Cadastrar fotos</h2>
            <form id="upload-form" action="{{ route('gallery.upload') }}" enctype="multipart/form-data">
                <input id="photos" name="photos[]" type="file" accept="image/jpeg,image/png" multiple required>
                <button type="submit">Enviar fotos</button>
            </form>
            <p id="upload-message" class="message" role="status" hidden></p>
        </section>
        <section class="panel"><h2>2. Encontrar minhas fotos</h2>
            <form id="search-form" action="{{ route('gallery.search') }}" enctype="multipart/form-data">
                <label for="selfie">Enviar uma selfie</label><input id="selfie" name="selfie" type="file" accept="image/jpeg,image/png">
                <div><button id="camera-open" class="secondary" type="button">Abrir câmera</button><button id="camera-capture" type="button" hidden>Capturar foto</button><button id="camera-close" class="secondary" type="button" hidden>Fechar câmera</button></div>
                <video id="camera" autoplay playsinline muted hidden></video><img id="preview" alt="Selfie escolhida para a busca" hidden>
                <label><input name="consent" type="checkbox" value="1" required> Autorizo o envio desta selfie à AWS para buscar minhas fotos.</label>
                <p><small>Use uma imagem com apenas seu rosto. A selfie não é salva na galeria.</small></p>
                <button type="submit">Buscar minhas fotos</button>
            </form>
            <p id="search-message" class="message" role="status" hidden></p>
        </section>
    </div>
    <section id="gallery-section"><h2 id="gallery-title">Fotos cadastradas</h2><button id="show-all" class="secondary" type="button" hidden>Mostrar todas as fotos</button><p id="gallery-count"></p><div id="gallery" class="grid"></div></section>
    <p><small>Similaridade mínima: {{ config('rekognition.threshold') }}%. Uma correspondência não garante identidade.</small></p>
</main>
<script>window.initialGallery = {{ Illuminate\Support\Js::from($photos) }};</script>
</body></html>
