// Centro Automotriz Arley — dashboard.php: drag-and-drop de órdenes entre columnas del Kanban

let draggedCard = null;
let justDragged = false;

document.querySelectorAll('.order-card[draggable="true"]').forEach((card) => {
    card.addEventListener('dragstart', (e) => {
        draggedCard = card;
        card.classList.add('dragging');
        e.dataTransfer.effectAllowed = 'move';
        e.dataTransfer.setData('text/plain', card.dataset.orderId);
    });

    card.addEventListener('dragend', () => {
        card.classList.remove('dragging');
        draggedCard = null;
    });

    // Un navegador puede disparar "click" justo después de soltar un
    // drag — evita que eso navegue accidentalmente al detalle de la orden.
    card.addEventListener('click', (e) => {
        if (justDragged) {
            e.preventDefault();
            justDragged = false;
        }
    });
});

document.querySelectorAll('.kanban-col').forEach((col) => {
    col.addEventListener('dragover', (e) => {
        if (!draggedCard) return;
        e.preventDefault();
        e.dataTransfer.dropEffect = 'move';
        col.classList.add('drag-over');
    });

    col.addEventListener('dragleave', (e) => {
        // Solo quita el resaltado si realmente salimos de la columna
        // (no al pasar entre hijos internos de la misma columna).
        if (!col.contains(e.relatedTarget)) {
            col.classList.remove('drag-over');
        }
    });

    col.addEventListener('drop', async (e) => {
        e.preventDefault();
        col.classList.remove('drag-over');
        if (!draggedCard) return;

        justDragged = true;

        const orderId = draggedCard.dataset.orderId;
        const newStatusId = parseInt(col.dataset.statusId, 10);
        const sourceCol = draggedCard.closest('.kanban-col');

        if (sourceCol === col) return; // soltado en la misma columna, no hacer nada

        const card = draggedCard;

        try {
            const res = await fetch('app/ajax/update_order_status.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({
                    csrf_token: DASHBOARD_CSRF,
                    order_id: orderId,
                    status_id: newStatusId,
                }),
            });
            const data = await res.json();

            if (!data.ok) {
                alert(data.error || 'No se pudo mover la orden.');
                return;
            }

            moveCardToColumn(card, sourceCol, col);
        } catch (err) {
            alert('Error de conexión al mover la orden.');
        }
    });
});

function moveCardToColumn(card, sourceCol, destCol) {
    const sourceCards = sourceCol.querySelector('.kanban-col-cards');
    const destCards = destCol.querySelector('.kanban-col-cards');

    destCards.appendChild(card);

    // Si la columna origen quedó vacía, mostrar el mensaje "Sin órdenes"
    if (!sourceCards.querySelector('.order-card')) {
        const empty = document.createElement('div');
        empty.className = 'empty-col';
        empty.textContent = 'Sin órdenes';
        sourceCards.appendChild(empty);
    }

    // Si la columna destino tenía el mensaje "Sin órdenes", quitarlo
    const destEmpty = destCards.querySelector('.empty-col');
    if (destEmpty) destEmpty.remove();

    updateColumnCount(sourceCol);
    updateColumnCount(destCol);
}

function updateColumnCount(col) {
    const count = col.querySelectorAll('.order-card').length;
    col.querySelector('.kanban-col-count').textContent = count;
}
