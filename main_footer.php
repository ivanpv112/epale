<?php
$footer_base_url = '../';
if (strpos($_SERVER['PHP_SELF'], '/admin/carga_masiva/') !== false || strpos($_SERVER['PHP_SELF'], '/admin/descarga_masiva/') !== false) {
    $footer_base_url = '../../';
}
?>
<footer class="main-footer">
    <div class="footer-content">
        <div class="footer-section footer-section-social">
            <div class="footer-social-wrapper">
                <img src="<?php echo $footer_base_url; ?>img/facebook.png" alt="Facebook" class="footer-social-img">
                <span class="footer-social-title">CUCEA PALE</span>
            </div>
        </div>
        <div class="footer-section footer-section-udg">
            <img src="<?php echo $footer_base_url; ?>img/logo-udg.png" alt="Universidad de Guadalajara" class="footer-logo-img">
        </div>
        <div class="footer-section footer-section-contact">
            <img src="<?php echo $footer_base_url; ?>img/isologo-pale.png" alt="PALE" class="footer-pale-img">
            <div class="footer-contact-details">
                <strong class="footer-contact-heading">Contacto</strong>
                <a href="tel:+523337703300" class="footer-contact-link footer-contact-phone">+52 (33)-3770-3300</a>
                <a href="mailto:plataforma.pale@cucea.udg.mx" class="footer-contact-link footer-contact-email">plataforma.pale@cucea.udg.mx</a>
            </div>
        </div>
    </div>
    <div class="address-bar">
        <p class="address-text">Periférico Norte N° 799, Núcleo Universitario Los Belenes, C.P. 45100, Zapopan, Jalisco, México.</p>
        <p class="copyright-text">Copyright © 2026 E-PALE</p>
    </div>
</footer>
