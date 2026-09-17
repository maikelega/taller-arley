// Taller Arley — order_new.php: autocomplete cliente/vehículo + escáner VIN

function escapeHtml(str) {
    const div = document.createElement('div');
    div.textContent = str == null ? '' : String(str);
    return div.innerHTML;
}

function debounce(fn, delay) {
    let timer = null;
    return function (...args) {
        clearTimeout(timer);
        timer = setTimeout(() => fn.apply(this, args), delay);
    };
}

// ── Autocomplete genérico ────────────────────────────────
function setupAutocomplete({ inputId, dropdownId, endpoint, renderItem, onSelect }) {
    const input = document.getElementById(inputId);
    const dropdown = document.getElementById(dropdownId);
    let items = [];
    let activeIndex = -1;

    const search = debounce(async (query) => {
        if (query.length < 2) {
            dropdown.classList.remove('open');
            return;
        }
        try {
            const res = await fetch(`${endpoint}?q=${encodeURIComponent(query)}`);
            items = await res.json();
        } catch (e) {
            items = [];
        }
        renderDropdown();
    }, 300);

    function renderDropdown() {
        activeIndex = -1;
        if (!items.length) {
            dropdown.innerHTML = '<div class="autocomplete-empty">Sin resultados — se creará como nuevo</div>';
            dropdown.classList.add('open');
            return;
        }
        dropdown.innerHTML = items.map((item, i) => `<div class="autocomplete-item" data-index="${i}">${renderItem(item)}</div>`).join('');
        dropdown.classList.add('open');

        dropdown.querySelectorAll('.autocomplete-item').forEach((el) => {
            el.addEventListener('click', () => {
                const idx = parseInt(el.dataset.index, 10);
                if (items[idx]) {
                    onSelect(items[idx]);
                    dropdown.classList.remove('open');
                }
            });
        });
    }

    input.addEventListener('input', (e) => search(e.target.value.trim()));

    input.addEventListener('keydown', (e) => {
        const els = dropdown.querySelectorAll('.autocomplete-item');
        if (!els.length) return;

        if (e.key === 'ArrowDown') {
            e.preventDefault();
            activeIndex = Math.min(activeIndex + 1, els.length - 1);
        } else if (e.key === 'ArrowUp') {
            e.preventDefault();
            activeIndex = Math.max(activeIndex - 1, 0);
        } else if (e.key === 'Enter' && activeIndex >= 0) {
            e.preventDefault();
            els[activeIndex].click();
            return;
        } else {
            return;
        }
        els.forEach((el, i) => el.classList.toggle('active', i === activeIndex));
    });

    document.addEventListener('click', (e) => {
        if (!input.contains(e.target) && !dropdown.contains(e.target)) {
            dropdown.classList.remove('open');
        }
    });
}

// ── Cliente ───────────────────────────────────────────────
setupAutocomplete({
    inputId: 'customer_name',
    dropdownId: 'customer_dropdown',
    endpoint: 'app/ajax/search_customers.php',
    renderItem: (c) => `<strong>${escapeHtml(c.name)}</strong><span>${escapeHtml(c.phone) || 'Sin teléfono'}</span>`,
    onSelect: (c) => {
        document.getElementById('customer_id').value = c.id;
        document.getElementById('customer_name').value = c.name;
        document.getElementById('customer_phone').value = c.phone || '';
        document.getElementById('customer_email').value = c.email || '';
    },
});

// Si el usuario edita el nombre después de seleccionar, invalida el customer_id
// (evita asociar la orden al cliente equivocado si cambia el texto)
document.getElementById('customer_name').addEventListener('input', () => {
    document.getElementById('customer_id').value = '';
});

// ── Vehículo (por placa) ─────────────────────────────────
setupAutocomplete({
    inputId: 'plate',
    dropdownId: 'plate_dropdown',
    endpoint: 'app/ajax/search_vehicle.php',
    renderItem: (v) => `<strong>${escapeHtml(v.plate)}</strong><span>${escapeHtml([v.brand, v.model, v.year].filter(Boolean).join(' ')) || 'Sin datos'} — ${escapeHtml(v.customer_name)}</span>`,
    onSelect: (v) => {
        document.getElementById('plate').value = v.plate;
        document.getElementById('vin').value = v.vin || '';
        document.getElementById('vehicle_brand').value = v.brand || '';
        document.getElementById('vehicle_model').value = v.model || '';
        document.getElementById('vehicle_year').value = v.year || '';

        // Autocompleta también el dueño del vehículo encontrado
        document.getElementById('customer_id').value = v.customer_id;
        document.getElementById('customer_name').value = v.customer_name;
        document.getElementById('customer_phone').value = v.customer_phone || '';
        document.getElementById('customer_email').value = v.customer_email || '';

        document.getElementById('vehicle_found_hint').style.display = 'block';
    },
});

// ── Contacto de entrega (si no es el dueño) ──────────────
document.getElementById('is_owner_checkbox').addEventListener('change', (e) => {
    document.getElementById('dropoff_fields').classList.toggle('open', !e.target.checked);
});

// ── Decodificar VIN (manual, botón) ──────────────────────
document.getElementById('decode_vin_btn').addEventListener('click', async () => {
    const vin = document.getElementById('vin').value.trim().toUpperCase();
    const status = document.getElementById('vin_decode_status');

    if (vin.length !== 17) {
        status.textContent = 'El VIN debe tener 17 caracteres.';
        status.style.color = '#fca5a5';
        return;
    }

    status.textContent = 'Consultando NHTSA...';
    status.style.color = '#94a3b8';

    try {
        const res = await fetch(`app/ajax/decode_vin.php?vin=${encodeURIComponent(vin)}`);
        const data = await res.json();

        if (!data.ok) {
            status.textContent = data.error || 'No se pudo decodificar el VIN.';
            status.style.color = '#fca5a5';
            return;
        }

        if (data.brand) document.getElementById('vehicle_brand').value = data.brand;
        if (data.model) document.getElementById('vehicle_model').value = data.model;
        if (data.year) document.getElementById('vehicle_year').value = data.year;

        status.textContent = '✓ Vehículo decodificado correctamente.';
        status.style.color = '#6ee7b7';
    } catch (e) {
        status.textContent = 'Error de conexión al decodificar.';
        status.style.color = '#fca5a5';
    }
});

// ══════════════════════════════════════════════════════════
// Escáner VIN (cámara) — código de barras (ZXing) + texto (Tesseract)
// Librerías se cargan solo al abrir el modal, para no pesar la carga inicial.
// ══════════════════════════════════════════════════════════

let zxingReader = null;
let scannerStream = null;
let scannerMode = 'barcode';

function loadScript(src) {
    return new Promise((resolve, reject) => {
        const s = document.createElement('script');
        s.src = src;
        s.onload = resolve;
        s.onerror = reject;
        document.head.appendChild(s);
    });
}

document.getElementById('open_scanner_btn').addEventListener('click', openScanner);
document.getElementById('close_scanner_btn').addEventListener('click', closeScanner);
document.getElementById('scanner_tab_barcode').addEventListener('click', () => switchScannerMode('barcode'));
document.getElementById('scanner_tab_text').addEventListener('click', () => switchScannerMode('text'));
document.getElementById('capture_text_btn').addEventListener('click', captureAndReadText);

async function openScanner() {
    document.getElementById('scanner_backdrop').classList.add('open');
    document.getElementById('scanner_status').textContent = 'Iniciando cámara...';

    try {
        scannerStream = await navigator.mediaDevices.getUserMedia({
            video: { facingMode: 'environment' },
        });
        document.getElementById('scanner_video').srcObject = scannerStream;
        document.getElementById('scanner_status').textContent = '';
        await switchScannerMode('barcode');
    } catch (e) {
        document.getElementById('scanner_status').textContent = 'No se pudo acceder a la cámara: ' + e.message;
    }
}

function closeScanner() {
    if (zxingReader) {
        zxingReader.reset();
        zxingReader = null;
    }
    if (scannerStream) {
        scannerStream.getTracks().forEach((t) => t.stop());
        scannerStream = null;
    }
    document.getElementById('scanner_backdrop').classList.remove('open');
}

async function switchScannerMode(mode) {
    scannerMode = mode;
    document.getElementById('scanner_tab_barcode').classList.toggle('active', mode === 'barcode');
    document.getElementById('scanner_tab_text').classList.toggle('active', mode === 'text');
    document.getElementById('capture_text_btn').style.display = mode === 'text' ? 'block' : 'none';

    if (zxingReader) {
        zxingReader.reset();
        zxingReader = null;
    }

    if (mode === 'barcode') {
        document.getElementById('scanner_status').textContent = 'Cargando lector de código de barras...';
        try {
            if (!window.ZXing) {
                await loadScript('https://cdn.jsdelivr.net/npm/@zxing/library@0.20.0/umd/index.min.js');
            }
            zxingReader = new window.ZXing.BrowserMultiFormatReader();
            document.getElementById('scanner_status').textContent = 'Apunta al código de barras del VIN (marco de la puerta).';

            zxingReader.decodeFromVideoElement(document.getElementById('scanner_video'), (result, err) => {
                if (result) {
                    const text = result.getText().toUpperCase().replace(/[^A-Z0-9]/g, '');
                    if (text.length === 17) {
                        document.getElementById('vin').value = text;
                        document.getElementById('scanner_status').textContent = '✓ VIN leído: ' + text;
                        setTimeout(closeScanner, 800);
                    }
                }
            });
        } catch (e) {
            document.getElementById('scanner_status').textContent = 'No se pudo cargar el lector de código de barras.';
        }
    } else {
        document.getElementById('scanner_status').textContent = 'Apunta al VIN impreso o grabado y presiona "Capturar".';
    }
}

async function captureAndReadText() {
    const status = document.getElementById('scanner_status');
    status.textContent = 'Cargando OCR...';

    try {
        if (!window.Tesseract) {
            await loadScript('https://cdn.jsdelivr.net/npm/tesseract.js@5/dist/tesseract.min.js');
        }

        const video = document.getElementById('scanner_video');
        const canvas = document.createElement('canvas');
        canvas.width = video.videoWidth;
        canvas.height = video.videoHeight;
        canvas.getContext('2d').drawImage(video, 0, 0);

        status.textContent = 'Leyendo texto (puede tardar unos segundos)...';

        const { data } = await window.Tesseract.recognize(canvas, 'eng');
        const raw = data.text.toUpperCase().replace(/[^A-Z0-9]/g, '');

        // Busca una subcadena de 17 caracteres alfanuméricos (formato VIN válido, sin I/O/Q)
        const match = raw.match(/[A-HJ-NPR-Z0-9]{17}/);

        if (match) {
            document.getElementById('vin').value = match[0];
            status.textContent = '✓ VIN detectado: ' + match[0] + ' (verifica que sea correcto)';
            setTimeout(closeScanner, 1200);
        } else {
            status.textContent = 'No se detectó un VIN válido (17 caracteres). Intenta de nuevo con mejor luz/enfoque.';
        }
    } catch (e) {
        status.textContent = 'Error al leer el texto: ' + e.message;
    }
}
