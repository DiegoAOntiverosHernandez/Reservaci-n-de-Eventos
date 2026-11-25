<?php
require_once __DIR__ . '/../php/auth.php';

// Exigir inicio de sesión
requireLogin();

$event_id = isset($_GET['event_id']) ? (int)$_GET['event_id'] : 0;
$espacios = isset($_GET['espacios']) ? (int)$_GET['espacios'] : 1;
$current_slots = isset($_GET['current_slots']) ? (int)$_GET['current_slots'] : 0;
$asientos_str = isset($_GET['asientos']) ? $_GET['asientos'] : '';
$lock_token = isset($_GET['lock_token']) ? trim($_GET['lock_token']) : '';

if ($event_id <= 0 || $espacios <= 0) {
    die('<h2>Error</h2><p>Parámetros inválidos.</p>');
}

try {
    // Obtener detalles del evento y categoría
    $stmt = $pdo->prepare("
        SELECT e.*, c.nombre_categoria
        FROM Evento e
        LEFT JOIN Categoria c ON e.id_categoria = c.id_categoria
        WHERE e.id_evento = ?
        LIMIT 1
    ");
    $stmt->execute([$event_id]);
    $event = $stmt->fetch();

    if (!$event) {
        die('<h2>Error</h2><p>El evento solicitado no existe.</p>');
    }

    // Obtener precios de zonas para el cálculo exacto
    $stmtZones = $pdo->prepare("SELECT id_zona, nombre_zona, precio FROM Zona_Boleto WHERE id_evento = ?");
    $stmtZones->execute([$event_id]);
    $dbZones = $stmtZones->fetchAll();

    $zonePrices = [];
    $zoneIds = [];
    foreach ($dbZones as $z) {
        $zonePrices[$z['nombre_zona']] = (float)$z['precio'];
        $zoneIds[$z['nombre_zona']] = (int)$z['id_zona'];
    }

    $nombre_zona = 'General';
    $id_zona = 0;
    $precio_unitario = 0.00;

    if (!empty($asientos_str)) {
        // Obtener zona a partir de los prefijos de los asientos elegidos: V (VIP), A (Zona A), B (Zona B), C (Zona C), D (Zona D)
        $firstSeat = explode(',', $asientos_str)[0];
        $prefix = substr(trim($firstSeat), 0, 1);
        
        if ($prefix === 'V') $nombre_zona = 'VIP';
        elseif ($prefix === 'A') $nombre_zona = 'Zona A';
        elseif ($prefix === 'B') $nombre_zona = 'Zona B';
        elseif ($prefix === 'C') $nombre_zona = 'Zona C';
        elseif ($prefix === 'D') $nombre_zona = 'Zona D';
        
        $precio_unitario = (float)($zonePrices[$nombre_zona] ?? 0.00);
        $id_zona = (int)($zoneIds[$nombre_zona] ?? 0);
    }

    // Fallback si no se encontró zona válida
    if ($id_zona === 0 && !empty($dbZones)) {
        $nombre_zona = $dbZones[0]['nombre_zona'];
        $precio_unitario = (float)$dbZones[0]['precio'];
        $id_zona = (int)$dbZones[0]['id_zona'];
    }

    $monto_total = $precio_unitario * $espacios;

} catch (Exception $e) {
    die('Error en el servidor: ' . $e->getMessage());
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link rel="stylesheet" type="text/css" href="../Assets/Styles/home_styles/nav_footer.css">
    <link href="https://fonts.googleapis.com/css2?family=Outfit:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">
    <title>Realizar Pago - Agora</title>
    <link rel="shortcut icon" href="../Assets/img/logo.png" type="image/x-icon">
    <link rel="stylesheet" type="text/css" href="../Assets/Styles/styles_events/pagar.css?v=<?php echo time(); ?>">
</head>
<body>
    <div class="nav">
        <div class="logo"><a href="../index.php"><img src="../Assets/img/title.png" alt="Agora"></a></div>
    </div>

    <div class="main-container">
        <div class="payment-box">
            <h1>Realizar pago</h1>
            <p class="subtitle">Ingrese los datos de tu tarjeta para completar la reservación</p>

            <div class="summary-bar">
                <div class="summary-details">
                    <h3>Resumen de compra</h3>
                    <p><?php echo htmlspecialchars($event['nombre_evento']); ?> - <?php echo $espacios; ?> boletos (<?php echo htmlspecialchars($asientos_str); ?>)</p>
                </div>
                <div class="summary-price">
                    $<?php echo number_format($monto_total, 2); ?> MXN
                </div>
            </div>

            <form id="paymentForm">
                <div class="grid-layout">
                    <!-- Columna de inputs -->
                    <div class="form-column">
                        <div class="form-group">
                            <label for="cardName">Nombre del titular</label>
                            <input type="text" id="cardName" placeholder="Como aparece en la tarjeta" required autocomplete="off">
                        </div>

                        <div class="form-group">
                            <label for="cardNumber">Número de tarjeta</label>
                            <input type="text" id="cardNumber" placeholder="0000 0000 0000 0000" maxlength="19" required autocomplete="off">
                        </div>

                        <div class="form-row-half">
                            <div class="form-group">
                                <label>Fecha de expiración</label>
                                <div class="expiry-inputs">
                                    <input type="text" id="expiryMonth" placeholder="Mes" maxlength="2" required autocomplete="off">
                                    <span>/</span>
                                    <input type="text" id="expiryYear" placeholder="Año" maxlength="2" required autocomplete="off">
                                </div>
                            </div>

                            <div class="form-group">
                                <label for="cardCvv">Código de seguridad</label>
                                <input type="text" id="cardCvv" placeholder="CVV" maxlength="4" required autocomplete="off">
                            </div>
                        </div>
                    </div>

                    <!-- Columna de tarjeta digital -->
                    <div class="card-column">
                        <div class="credit-card">
                            <div class="card-top">
                                <div class="card-chip"></div>
                                <div style="font-size: 1.1rem; font-weight: 800; font-style: italic; opacity: 0.85;">Agora</div>
                            </div>
                            
                            <div class="card-number" id="previewNumber">•••• •••• •••• ••••</div>
                            
                            <div class="card-bottom">
                                <div class="card-bottom-item">
                                    <div class="card-bottom-label">Titular</div>
                                    <div class="card-bottom-value" id="previewName">Nombre Apellido</div>
                                </div>
                                <div class="card-bottom-item" style="text-align: right;">
                                    <div class="card-bottom-label">Vence</div>
                                    <div class="card-bottom-value" id="previewExpiry">MM/AA</div>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Botón de pago -->
                    <div class="btn-pay-container">
                        <button type="submit" id="btnPay" class="btn-pay">Pagar $<?php echo number_format($monto_total, 2); ?> MXN</button>
                    </div>
                </div>
            </form>

            <!-- Marcas Aceptadas -->
            <div class="accepted-brands">
                <div class="brand-section">
                    <span class="brand-section-title">Tarjetas de crédito</span>
                    <div class="brand-logos">
                        <div class="logo-badge logo-visa">VISA</div>
                        <div class="logo-badge logo-mastercard"></div>
                        <div class="logo-badge logo-amex">AMEX</div>
                        <div class="logo-badge logo-carnet">CARNET</div>
                    </div>
                </div>
                <div style="width: 1px; height: 30px; background-color: #cbd5e1; display: inline-block;"></div>
                <div class="brand-section">
                    <span class="brand-section-title">Tarjetas de débito</span>
                    <div class="brand-logos">
                        <div class="logo-badge logo-bank">BBVA</div>
                        <div class="logo-badge logo-bank" style="color: #ef4444;">Santander</div>
                        <div class="logo-badge logo-bank" style="color: #047857;">HSBC</div>
                        <div class="logo-badge logo-bank" style="color: #ea580c;">Scotiabank</div>
                        <div class="logo-badge logo-bank" style="font-size: 0.65rem;">INBURSA</div>
                        <div class="logo-badge logo-bank" style="color: #15803d;">IXE</div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <div id="footer">
        <footer class="footer">
            <div class="footer_content">
                <div class="name">
                    <img src="../Assets/img/title.png" alt="Agora">
                </div>
                <div class="contact_info">
                    Contáctanos <br>
                    Av. Calle falso #1234 <br>
                    <a href="mailto:Agoraeventos@correofalso.com">Agoraeventos@correofalso.com</a> <br>
                    012 000 45-150 <br><br>
                    © 2026 Agora Todos los derechos reservados.
                </div>
                <div class="div_3"></div>
            </div>
        </footer>
    </div>

    <!-- Script de Interactividad y Procesamiento -->
    <script>
        document.addEventListener('DOMContentLoaded', () => {
            const form = document.getElementById('paymentForm');
            const cardName = document.getElementById('cardName');
            const cardNumber = document.getElementById('cardNumber');
            const expiryMonth = document.getElementById('expiryMonth');
            const expiryYear = document.getElementById('expiryYear');
            const cardCvv = document.getElementById('cardCvv');
            const btnPay = document.getElementById('btnPay');

            // Previews
            const previewName = document.getElementById('previewName');
            const previewNumber = document.getElementById('previewNumber');
            const previewExpiry = document.getElementById('previewExpiry');

            // Sincronizar Nombre
            cardName.addEventListener('input', (e) => {
                const value = e.target.value;
                previewName.textContent = value.trim() ? value : 'Nombre Apellido';
            });

            // Formatear y sincronizar número de tarjeta
            cardNumber.addEventListener('input', (e) => {
                let value = e.target.value.replace(/\s+/g, '').replace(/[^0-9]/gi, '');
                let formatted = '';
                for (let i = 0; i < value.length; i++) {
                    if (i > 0 && i % 4 === 0) {
                        formatted += ' ';
                    }
                    formatted += value[i];
                }
                e.target.value = formatted;

                // Actualizar preview
                let previewText = formatted;
                while (previewText.length < 19) {
                    let nextIdx = previewText.length;
                    if (nextIdx === 4 || nextIdx === 9 || nextIdx === 14) {
                        previewText += ' ';
                    } else {
                        previewText += '•';
                    }
                }
                previewNumber.textContent = previewText;
            });

            // Sincronizar fecha de expiración
            function updateExpiryPreview() {
                const m = expiryMonth.value.replace(/[^0-9]/g, '');
                const y = expiryYear.value.replace(/[^0-9]/g, '');
                
                let mm = m.padStart(2, '0');
                let yy = y.padStart(2, '0');
                
                if (!m && !y) {
                    previewExpiry.textContent = 'MM/AA';
                } else {
                    previewExpiry.textContent = `${m ? mm : 'MM'}/${y ? yy : 'AA'}`;
                }
            }

            expiryMonth.addEventListener('input', (e) => {
                let value = e.target.value.replace(/[^0-9]/g, '');
                if (value.length > 0 && parseInt(value) > 12) {
                    value = '12';
                }
                e.target.value = value;
                updateExpiryPreview();
            });

            expiryYear.addEventListener('input', (e) => {
                e.target.value = e.target.value.replace(/[^0-9]/g, '');
                updateExpiryPreview();
            });

            // CVV numérico
            cardCvv.addEventListener('input', (e) => {
                e.target.value = e.target.value.replace(/[^0-9]/g, '');
            });

            // Procesar envío
            form.addEventListener('submit', async (e) => {
                e.preventDefault();

                // Validaciones de tarjeta ficticia robusta
                const num = cardNumber.value.replace(/\s+/g, '');
                if (num.length < 15) {
                    alert('Por favor ingrese un número de tarjeta válido.');
                    return;
                }

                btnPay.disabled = true;
                const originalText = btnPay.textContent;
                btnPay.textContent = 'Procesando pago...';

                // Enviar la petición a la base de datos incluyendo el token de bloqueo de esta ventana
                const dataToSend = {
                    event_id: <?php echo $event_id; ?>,
                    espacios: <?php echo $espacios; ?>,
                    current_slots: <?php echo $current_slots; ?>,
                    asientos: '<?php echo addslashes($asientos_str); ?>',
                    lock_token: '<?php echo addslashes($lock_token); ?>' || window.name || ''
                };

                try {
                    const response = await fetch('../php/make_reservation.php', {
                        method: 'POST',
                        headers: {
                            'Content-Type': 'application/json',
                            'Accept': 'application/json'
                        },
                        body: JSON.stringify(dataToSend)
                    });

                    if (!response.ok) {
                        throw new Error(`Error de servidor: ${response.status}`);
                    }

                    const result = await response.json();

                    if (result.success) {
                        // El id_reservacion se devuelve para redirigir a boleto.php
                        const resId = result.id_reservacion;
                        
                        alert('¡Pago procesado con éxito!');
                        // Redirigir a boleto.php
                        if (resId) {
                            window.location.href = `boleto.php?reservacion_id=${resId}&asientos=${encodeURIComponent('<?php echo $asientos_str; ?>')}`;
                        } else {
                            window.location.href = 'historial_r.php';
                        }
                    } else {
                        alert('Fallo en la transacción: ' + result.message);
                        btnPay.disabled = false;
                        btnPay.textContent = originalText;
                    }
                } catch (error) {
                    console.error('Error:', error);
                    alert('Hubo un error de conexión al procesar el pago.');
                    btnPay.disabled = false;
                    btnPay.textContent = originalText;
                }
            });
        });
    </script>
</body>
</html>
