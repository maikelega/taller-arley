// Centro Automotriz Arley — order_detail.php: inspección visual (fotos)

const ANGLE_LABELS = {
    front: 'Frente', back: 'Atrás', left: 'Lateral izquierdo', right: 'Lateral derecho',
    roof: 'Techo / capó', interior: 'Interior / tablero',
    wheel_fl: 'Llanta del. izquierda', wheel_fr: 'Llanta del. derecha',
    wheel_rl: 'Llanta tras. izquierda', wheel_rr: 'Llanta tras. derecha',
    extra: 'Foto adicional',
};

function escapeHtml(str) {
    const div = document.createElement('div');
    div.textContent = str == null ? '' : String(str);
    return div.innerHTML;
}

const fileInput = document.getElementById('photo_file_input');
let pendingAngle = null;

document.querySelectorAll('.photo-point').forEach((el) => {
    el.addEventListener('click', () => {
        pendingAngle = el.dataset.angle;
        fileInput.click();
    });
});

document.getElementById('add_extra_photo_btn').addEventListener('click', () => {
    pendingAngle = 'extra';
    fileInput.click();
});

fileInput.addEventListener('change', async () => {
    const file = fileInput.files[0];
    fileInput.value = '';
    if (!file || !pendingAngle) return;

    const angle = pendingAngle;
    pendingAngle = null;

    const formData = new FormData();
    formData.append('csrf_token', ORDER_DETAIL_CSRF);
    formData.append('order_id', ORDER_DETAIL_ID);
    formData.append('angle', angle);
    formData.append('photo', file);

    try {
        const res = await fetch('app/ajax/upload_photo.php', { method: 'POST', body: formData });
        const data = await res.json();

        if (!data.ok) {
            alert(data.error || 'No se pudo subir la foto.');
            return;
        }

        // Marca el punto del diagrama como completado (si tiene ángulo fijo)
        const point = document.querySelector(`.photo-point[data-angle="${angle}"]`);
        if (point) {
            point.dataset.hasPhoto = '1';
            point.querySelector('.photo-point-icon').textContent = '✓';
            const legendDot = document.querySelector(`.legend-item[data-angle="${angle}"] .legend-dot`);
            if (legendDot) legendDot.classList.add('filled');
        }

        addThumbnail(data.photo_id, data.url, angle);
    } catch (e) {
        alert('Error de conexión al subir la foto.');
    }
});

function addThumbnail(photoId, url, angle) {
    const container = document.getElementById('photo_thumbnails');
    const existing = container.querySelector(`.photo-thumb[data-photo-id="${photoId}"]`);
    if (existing) existing.remove();

    // Para ángulos fijos (no "extra"), reemplaza la miniatura anterior de ese ángulo
    if (angle !== 'extra') {
        container.querySelectorAll('.photo-thumb').forEach((el) => {
            if (el.dataset.angle === angle) el.remove();
        });
    }

    const label = ANGLE_LABELS[angle] || angle;
    const div = document.createElement('div');
    div.className = 'photo-thumb';
    div.dataset.photoId = photoId;
    div.dataset.angle = angle;
    div.innerHTML = `
        <img src="${escapeHtml(url)}" alt="${escapeHtml(label)}">
        <span class="photo-thumb-label">${escapeHtml(label)}</span>
        <button type="button" class="photo-thumb-delete" data-photo-id="${photoId}">×</button>
    `;
    div.querySelector('.photo-thumb-delete').addEventListener('click', () => deletePhoto(photoId, angle, div));
    container.appendChild(div);
}

document.querySelectorAll('.photo-thumb-delete').forEach((btn) => {
    btn.addEventListener('click', () => {
        const thumb = btn.closest('.photo-thumb');
        const photoId = parseInt(btn.dataset.photoId, 10);
        const angle = thumb.dataset.angle;
        deletePhoto(photoId, angle, thumb);
    });
});

async function deletePhoto(photoId, angle, thumbEl) {
    if (!confirm('¿Eliminar esta foto?')) return;

    try {
        const res = await fetch('app/ajax/delete_photo.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ csrf_token: ORDER_DETAIL_CSRF, photo_id: photoId, order_id: ORDER_DETAIL_ID }),
        });
        const data = await res.json();

        if (!data.ok) {
            alert(data.error || 'No se pudo eliminar la foto.');
            return;
        }

        thumbEl.remove();

        const point = document.querySelector(`.photo-point[data-angle="${angle}"]`);
        if (point) {
            point.dataset.hasPhoto = '0';
            point.querySelector('.photo-point-icon').textContent = '+';
            const legendDot = document.querySelector(`.legend-item[data-angle="${angle}"] .legend-dot`);
            if (legendDot) legendDot.classList.remove('filled');
        }
    } catch (e) {
        alert('Error de conexión al eliminar la foto.');
    }
}
