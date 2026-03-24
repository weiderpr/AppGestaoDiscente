<?php
/**
 * Vértice Acadêmico — Language Switcher Component
 * Para usar: include_once __DIR__ . '/language_switcher.php';
 */

$locales = [
    'pt-BR' => ['flag' => '🇧🇷', 'name' => 'Português'],
    'en-US' => ['flag' => '🇺🇸', 'name' => 'English'],
];

$current = I18n::getLocale();
$currentFlag = $locales[$current]['flag'] ?? '🌐';
$currentName = $locales[$current]['name'] ?? $current;
?>

<div class="language-switcher" style="position: relative; display: inline-flex; align-items: center; margin-right: 8px;">
    <button type="button" 
            id="langToggleBtn"
            style="background: var(--bg-surface); border: 1px solid var(--border-color); border-radius: var(--radius-md); padding: 4px 10px; cursor: pointer; display: flex; align-items: center; gap: 6px; font-size: 13px; color: var(--text-primary); height: 36px; white-space: nowrap;">
        <span><?php echo $currentFlag; ?></span>
        <span><?php echo $currentName; ?></span>
    </button>
    
    <div id="langDropdown" style="display: none; position: absolute; top: 100%; right: 0; margin-top: 4px; background: var(--bg-card); border: 1px solid var(--border-color); border-radius: var(--radius-md); box-shadow: 0 4px 12px rgba(0,0,0,0.15); min-width: 130px; z-index: 9999; overflow: hidden;">
        <?php foreach ($locales as $code => $info): ?>
            <?php if ($code !== $current): ?>
                <a href="javascript:;" 
                   onclick="setLanguage('<?php echo $code; ?>')"
                   style="display: flex; align-items: center; gap: 8px; padding: 8px 12px; color: var(--text-primary); text-decoration: none; font-size: 13px;">
                    <span><?php echo $info['flag']; ?></span>
                    <span><?php echo $info['name']; ?></span>
                </a>
            <?php endif; ?>
        <?php endforeach; ?>
    </div>
</div>

<script>
(function() {
    var toggleBtn = document.getElementById('langToggleBtn');
    var dropdown = document.getElementById('langDropdown');
    
    if (toggleBtn && dropdown) {
        toggleBtn.addEventListener('click', function(e) {
            e.stopPropagation();
            dropdown.style.display = dropdown.style.display === 'block' ? 'none' : 'block';
        });
    }
    
    document.addEventListener('click', function(e) {
        if (dropdown && dropdown.style.display === 'block') {
            if (!e.target.closest('.language-switcher')) {
                dropdown.style.display = 'none';
            }
        }
    });
})();

function setLanguage(locale) {
    var xhr = new XMLHttpRequest();
    xhr.open('POST', '/api/set_language.php?locale=' + locale, true);
    xhr.setRequestHeader('Content-Type', 'application/x-www-form-urlencoded');
    xhr.onload = function() {
        if (xhr.status === 200) {
            try {
                var response = JSON.parse(xhr.responseText);
                if (response.success) {
                    window.location.reload();
                } else {
                    alert('Erro ao trocar idioma');
                }
            } catch(e) {
                window.location.reload();
            }
        } else {
            alert('Erro ao trocar idioma');
        }
    };
    xhr.onerror = function() {
        alert('Erro ao trocar idioma');
    };
    xhr.send();
}
</script>
