document.addEventListener('DOMContentLoaded', function() {

    // Toggle switch interactivo de estado de evento
    const toggles = document.querySelectorAll('.toggle-switch input[type="checkbox"]');

    toggles.forEach(toggle => {
        toggle.addEventListener('change', async function() {
            const eventId = this.getAttribute('data-event-id');
            const isChecked = this.checked;
            const newStatus = isChecked ? 'activo' : 'cancelado';
            
            const card = this.closest('.event-card');
            const statusPill = card.querySelector('.event-status-pill');
            
            this.disabled = true;

            try {
                const response = await fetch('../php/toggle_status.php', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify({ 
                        event_id: eventId, 
                        status: newStatus 
                    })
                });

                const result = await response.json();

                if (result.success) {
                    if (statusPill) {
                        statusPill.textContent = newStatus.charAt(0).toUpperCase() + newStatus.slice(1);
                        statusPill.className = 'event-status-pill ' + newStatus;
                    }
                    card.setAttribute('data-estado', newStatus);
                    
                } else {
                    alert('Error: ' + result.message);
                    this.checked = !isChecked; 
                }

            } catch (error) {
                console.error('Error de conexión:', error);
                alert('Hubo un error de conexión.');
                this.checked = !isChecked; 
            } finally {
                this.disabled = false;
            }
        });
    });

    // Menú de desplegable de Acciones
    document.querySelectorAll('.actions-dropdown').forEach(button => {
        button.addEventListener('click', function(e) {
            e.stopPropagation();
            
            const content = this.nextElementSibling;
            
            document.querySelectorAll('.actions-dropdown.active').forEach(openBtn => {
                if (openBtn !== this) {
                    openBtn.classList.remove('active');
                }
            });
            this.classList.toggle('active');
        });
    });

    window.addEventListener('click', function() {
        document.querySelectorAll('.actions-dropdown.active').forEach(button => {
            button.classList.remove('active');
        });
    });

    // Acción para Eliminar Evento
    document.querySelectorAll('.action-delete').forEach(button => {
        button.addEventListener('click', async function(e) {
            e.preventDefault();
            e.stopPropagation(); 

            const eventId = this.getAttribute('data-event-id');
            const confirmDelete = window.confirm('¿Estás seguro de que quieres eliminar este evento? Esta acción es irreversible.');

            if (!confirmDelete) {
                return;
            }
            
            try {
                const response = await fetch('../php/delete_event.php', {
                    method: 'POST', 
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify({ event_id: eventId })
                });

                const result = await response.json();

                if (result.success) {
                    alert(result.message);
                    window.location.reload(); 
                } else {
                    alert('Fallo al eliminar el evento: ' + result.message);
                }

            } catch (error) {
                console.error('Error al intentar eliminar:', error);
                alert('Error de conexión o servidor al eliminar el evento.');
            }
        });
    });
});

// Filtros y búsquedas globales (disponibles en el objeto window)
window.filterEvents = function() {
    const searchVal = document.getElementById('searchInput').value.toLowerCase().trim();
    const categoryVal = document.getElementById('filterCategory').value;
    const cards = document.querySelectorAll('.event-card');
    
    cards.forEach(card => {
        const title = card.querySelector('.event-title').textContent.toLowerCase();
        const category = card.getAttribute('data-category') || '';
        
        const matchesSearch = title.includes(searchVal);
        const matchesCategory = (categoryVal === 'all' || category.includes(categoryVal.toLowerCase()));
        
        if (matchesSearch && matchesCategory) {
            card.style.display = 'grid';
        } else {
            card.style.display = 'none';
        }
    });
};

window.sortEvents = function() {
    const sortVal = document.getElementById('sortDate').value;
    const container = document.querySelector('.events-container');
    const cards = Array.from(document.querySelectorAll('.event-card'));
    
    cards.sort((a, b) => {
        const timeA = parseInt(a.getAttribute('data-timestamp'));
        const timeB = parseInt(b.getAttribute('data-timestamp'));
        
        if (sortVal === 'recent') {
            return timeB - timeA;
        } else {
            return timeA - timeB;
        }
    });
    
    // Volver a añadir las tarjetas ordenadas
    cards.forEach(card => container.appendChild(card));
};