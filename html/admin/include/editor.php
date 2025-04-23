<?php
/**
 * Stimma - Lär dig i små steg
 * Copyright (C) 2025 Christian Alfredsson
 * 
 * This program is free software; licensed under GPL v2.
 * See LICENSE and LICENSE-AND-TRADEMARK.md for details.
 * 
 * The name "Stimma" is a trademark and subject to restrictions.
 */
?>

<?php
/**
 * Enkel texteditor med grundläggande formatering
 * 
 * @param string $content Innehållet som ska redigeras
 * @param string $name Namnet på input-fältet
 * @param string $id ID för editorn (default: 'editor')
 */
function renderEditor($content, $name = 'content', $id = 'editor') {
    ?>
    <div class="editor-toolbar">
        <button type="button" onclick="formatText('bold', '<?= $id ?>')" title="Fet">
            <i class="bi bi-type-bold"></i>
        </button>
        <button type="button" onclick="formatText('italic', '<?= $id ?>')" title="Kursiv">
            <i class="bi bi-type-italic"></i>
        </button>
        <button type="button" onclick="formatText('underline', '<?= $id ?>')" title="Understruken">
            <i class="bi bi-type-underline"></i>
        </button>
        <button type="button" onclick="formatList('bullet', '<?= $id ?>')" title="Punktlista">
            <i class="bi bi-list-ul"></i>
        </button>
        <button type="button" onclick="formatList('number', '<?= $id ?>')" title="Nummerlista">
            <i class="bi bi-list-ol"></i>
        </button>
        <button type="button" onclick="cleanFormat('<?= $id ?>')" title="Rensa format">
            <i class="bi bi-eraser"></i>
        </button>
    </div>
    <div id="<?= $id ?>" class="editor-content" contenteditable="true"><?= $content ?></div>
    <input type="hidden" name="<?= $name ?>" id="<?= $id ?>Input">
    <?php
}
?>

<style>
.editor-toolbar {
    background: #f8f9fa;
    border: 1px solid #dee2e6;
    border-bottom: none;
    padding: 0.5rem;
    border-radius: 0.25rem 0.25rem 0 0;
    display: flex;
    gap: 0.25rem;
}
.editor-toolbar button {
    background: none;
    border: none;
    padding: 0.25rem 0.5rem;
    border-radius: 0.25rem;
    color: var(--dark-gray);
}
.editor-toolbar button:hover {
    background: #e9ecef;
}
.editor-toolbar button.active {
    background: #e9ecef;
}
.editor-content {
    border: 1px solid #dee2e6;
    padding: 1rem;
    min-height: 300px;
    border-radius: 0 0 0.25rem 0.25rem;
    line-height: 1.6;
}
.editor-content:focus {
    outline: none;
    border-color: var(--accent-blue);
}
</style>

<script>
function formatText(command, editorId) {
    document.execCommand(command, false, null);
    document.getElementById(editorId).focus();
}

function formatList(type, editorId) {
    document.execCommand(type === 'bullet' ? 'insertUnorderedList' : 'insertOrderedList', false, null);
    document.getElementById(editorId).focus();
}

function cleanFormat(editorId) {
    const editor = document.getElementById(editorId);
    const content = editor.innerHTML;
    
    // Skapa en temporär div för att rensa innehållet
    const tempDiv = document.createElement('div');
    tempDiv.innerHTML = content;
    
    // Ta bort alla attribut och stilar som inte är tillåtna
    const elements = tempDiv.getElementsByTagName('*');
    for (let element of elements) {
        // Behåll bara tillåtna taggar och attribut
        if (!['B', 'STRONG', 'I', 'EM', 'U', 'UL', 'OL', 'LI', 'BR', 'P', 'DIV'].includes(element.tagName)) {
            // Ersätt otillåtna element med deras innehåll
            const parent = element.parentNode;
            while (element.firstChild) {
                parent.insertBefore(element.firstChild, element);
            }
            parent.removeChild(element);
        } else {
            // Ta bort alla attribut utom style för tillåtna element
            const attributes = element.attributes;
            for (let i = attributes.length - 1; i >= 0; i--) {
                const attr = attributes[i];
                if (attr.name !== 'style') {
                    element.removeAttribute(attr.name);
                }
            }
            
            // Behåll bara tillåtna stilar
            if (element.style) {
                const allowedStyles = ['font-weight', 'font-style', 'text-decoration'];
                for (let style of element.style) {
                    if (!allowedStyles.includes(style)) {
                        element.style[style] = '';
                    }
                }
            }
        }
    }
    
    // Uppdatera editorn med det rensade innehållet
    editor.innerHTML = tempDiv.innerHTML;
    editor.focus();
}

// Spara innehållet i hidden input innan formuläret skickas
document.addEventListener('DOMContentLoaded', function() {
    const forms = document.querySelectorAll('form');
    forms.forEach(form => {
        form.addEventListener('submit', function() {
            // Hitta alla editorer i formuläret
            const editors = form.querySelectorAll('.editor-content');
            editors.forEach(editor => {
                const editorId = editor.id;
                const inputId = editorId + 'Input';
                const input = document.getElementById(inputId);
                if (input) {
                    input.value = editor.innerHTML;
                }
            });
        });
    });

    // Lägg till paste-hanterare för alla editorer
    const editors = document.querySelectorAll('.editor-content');
    editors.forEach(editor => {
        editor.addEventListener('paste', function(e) {
            // Tillåt standard paste-beteendet att köra först
            setTimeout(() => {
                // Rensa formateringen efter att texten har klistrats in
                cleanFormat(editor.id);
            }, 0);
        });
    });
});
</script> 