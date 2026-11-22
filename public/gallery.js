(() => {
    const $ = id => document.getElementById(id);
    let photos = window.initialGallery || [];
    let stream = null;
    let captured = null;
    let previewUrl = null;
    const token = document.querySelector('meta[name="csrf-token"]').content;
    const labels = { indexed: 'Pronta para busca', no_faces: 'Sem rostos utilizáveis', failed: 'Falha na análise', processing: 'Processando' };
    function message(id, text, error = false) {
        const node = $(id);
        node.hidden = false;
        node.textContent = text;
        node.classList.toggle('error', error);
    }
    async function request(url, data) {
        const response = await fetch(url, { method: 'POST', headers: { 'X-CSRF-TOKEN': token, Accept: 'application/json' }, body: data });
        const result = await response.json().catch(() => ({}));
        if (!response.ok) {
            throw new Error(result.errors ? Object.values(result.errors).flat().join('\n') : result.message || `Falha HTTP ${response.status}. Confira os limites de upload do PHP e sua conexão.`);
        }
        return result;
    }
    function render(items, filtered = false) {
        $('gallery').replaceChildren();
        $('gallery-title').textContent = filtered ? 'Fotos em que você aparece' : 'Fotos cadastradas';
        $('gallery-count').textContent = `${items.length} foto(s)${items.length === 0 ? (filtered ? ' encontrada(s). Tente outra selfie ou cadastre mais fotos.' : '. Cadastre sua primeira foto acima.') : '.'}`;
        $('show-all').hidden = !filtered;
        for (const photo of items) {
            const figure = document.createElement('figure'); figure.className = 'photo';
            const link = document.createElement('a'); link.href = photo.url; link.target = '_blank'; link.rel = 'noopener';
            const image = document.createElement('img'); image.src = photo.url; image.alt = photo.name; image.loading = 'lazy'; link.append(image);
            const caption = document.createElement('figcaption'); caption.className = 'caption';
            const name = document.createElement('strong'); name.textContent = photo.name;
            const status = document.createElement('p'); status.textContent = `${labels[photo.status]} · ${photo.faces} rosto(s) indexado(s)`;
            caption.append(name, status);
            if (photo.unindexed_faces) { const warning = document.createElement('p'); warning.textContent = `${photo.unindexed_faces} rosto(s) sem qualidade suficiente para indexação.`; caption.append(warning); }
            if (photo.similarity !== undefined) { const score = document.createElement('p'); score.className = 'score'; score.textContent = `${photo.similarity.toFixed(2)}% de similaridade`; caption.append(score); }
            if (photo.error) { const error = document.createElement('p'); error.textContent = photo.error; caption.append(error); }
            if (photo.status === 'failed') {
                const retry = document.createElement('button'); retry.type = 'button'; retry.textContent = 'Tentar análise novamente';
                retry.addEventListener('click', async () => {
                    retry.disabled = true;
                    try { const result = await request(photo.retry_url, new FormData()); photos = photos.map(item => item.id === result.photo.id ? result.photo : item); render(photos); }
                    catch (error) { message('upload-message', error.message, true); }
                    finally { retry.disabled = false; }
                });
                caption.append(retry);
            }
            figure.append(link, caption); $('gallery').append(figure);
        }
    }
    function validateFile(file) {
        if (!file || !['image/jpeg', 'image/png'].includes(file.type)) { throw new Error('Selecione uma imagem JPG ou PNG.'); }
        if (file.size > 5 * 1024 * 1024) { throw new Error(`${file.name}: limite de 5 MB por imagem.`); }
    }
    $('upload-form').addEventListener('submit', async event => {
        event.preventDefault();
        const files = Array.from($('photos').files);
        try { if (!files.length || files.length > 10) { throw new Error('Selecione entre 1 e 10 fotos.'); } files.forEach(validateFile); }
        catch (error) { message('upload-message', error.message, true); return; }
        const button = event.target.querySelector('button'); button.disabled = true; $('photos').disabled = true;
        const failures = []; let indexed = 0; let saved = 0;
        try {
            for (let index = 0; index < files.length; index++) {
                message('upload-message', `Enviando ${index + 1}/${files.length}: ${files[index].name}`);
                const data = new FormData(); data.append('photos[]', files[index]);
                try {
                    const result = await request(event.target.action, data);
                    photos = [...result.photos, ...photos]; saved += result.photos.length;
                    indexed += result.photos.filter(photo => photo.status === 'indexed').length;
                    for (const photo of result.photos.filter(photo => photo.status === 'failed')) { failures.push(`${photo.name}: ${photo.error}`); }
                    render(photos);
                } catch (error) { failures.push(`${files[index].name}: ${error.message}`); }
            }
            message('upload-message', `${saved}/${files.length} foto(s) salva(s); ${indexed} pronta(s) para busca.${failures.length ? '\n' + failures.join('\n') : ''}`, failures.length > 0);
            if (!failures.length) { event.target.reset(); }
        } finally { button.disabled = false; $('photos').disabled = false; }
    });
    function stopCamera() {
        if (stream) { stream.getTracks().forEach(track => track.stop()); stream = null; }
        $('camera').srcObject = null; $('camera').hidden = true; $('camera-capture').hidden = true; $('camera-close').hidden = true;
    }
    function preview(file) {
        if (previewUrl) { URL.revokeObjectURL(previewUrl); }
        previewUrl = file ? URL.createObjectURL(file) : null;
        $('preview').hidden = !file;
        if (file) { $('preview').src = previewUrl; } else { $('preview').removeAttribute('src'); }
    }
    $('selfie').addEventListener('change', () => { captured = null; stopCamera(); preview($('selfie').files[0]); });
    $('camera-open').addEventListener('click', async () => {
        const button = $('camera-open'); button.disabled = true;
        try {
            stopCamera();
            if (!navigator.mediaDevices?.getUserMedia) { throw new Error('Câmera indisponível. Abra em localhost/HTTPS ou envie uma foto.'); }
            stream = await navigator.mediaDevices.getUserMedia({ video: { facingMode: 'user', width: { ideal: 1280 }, height: { ideal: 720 } }, audio: false });
            $('camera').srcObject = stream; $('camera').hidden = false; await $('camera').play();
            $('camera-capture').hidden = false; $('camera-close').hidden = false;
        } catch (error) { stopCamera(); message('search-message', error.name === 'NotAllowedError' ? 'Permita o acesso à câmera no navegador ou envie uma foto.' : error.message, true); }
        finally { button.disabled = false; }
    });
    $('camera-close').addEventListener('click', stopCamera);
    $('camera-capture').addEventListener('click', () => {
        const video = $('camera');
        if (!video.videoWidth) { message('search-message', 'Aguarde a câmera iniciar.', true); return; }
        const canvas = document.createElement('canvas'); canvas.width = video.videoWidth; canvas.height = video.videoHeight;
        canvas.getContext('2d').drawImage(video, 0, 0);
        canvas.toBlob(blob => {
            if (!blob) { message('search-message', 'Não foi possível capturar a foto.', true); return; }
            captured = new File([blob], 'selfie-camera.jpg', { type: 'image/jpeg' });
            $('selfie').value = ''; preview(captured); stopCamera(); message('search-message', 'Foto capturada. Autorize o processamento e clique em buscar.');
        }, 'image/jpeg', 0.9);
    });
    $('search-form').addEventListener('submit', async event => {
        event.preventDefault();
        const selfie = captured || $('selfie').files[0];
        try { validateFile(selfie); } catch (error) { message('search-message', error.message, true); return; }
        const button = event.target.querySelector('button[type=submit]'); button.disabled = true; stopCamera();
        message('search-message', 'Buscando correspondências...');
        try {
            const data = new FormData(event.target); data.set('selfie', selfie);
            const result = await request(event.target.action, data); render(result.photos, true);
            message('search-message', result.limit_reached ? 'Limite de 4.096 rostos atingido. Os resultados podem estar incompletos.' : `Busca concluída: ${result.photos.length} foto(s) encontrada(s).`);
        } catch (error) { message('search-message', error.message, true); }
        finally { button.disabled = false; }
    });
    $('show-all').addEventListener('click', () => render(photos));
    window.addEventListener('pagehide', () => { stopCamera(); if (previewUrl) { URL.revokeObjectURL(previewUrl); } });
    render(photos);
})();
