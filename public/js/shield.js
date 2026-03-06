/**
 * UAZAPI CLIENT-SIDE SHIELD
 * Oculta contexto, bloqueia DevTools e Inspecionar Elemento.
 */
(function () {
    // 1. Desativar Botão Direito (Menu de Contexto)
    document.addEventListener('contextmenu', function (e) {
        e.preventDefault();
        return false;
    });

    // 2. Interceptar Atalhos de Teclado
    document.addEventListener('keydown', function (e) {
        // F12
        if (e.key === 'F12' || e.keyCode === 123) {
            e.preventDefault();
            return false;
        }

        // Ctrl+Shift+I (Inspecionar) / Ctrl+Shift+J (Console) / Ctrl+Shift+C (Elemento)
        if (e.ctrlKey && e.shiftKey && (e.key === 'I' || e.key === 'i' || e.key === 'J' || e.key === 'j' || e.key === 'C' || e.key === 'c')) {
            e.preventDefault();
            return false;
        }

        // Ctrl+U (Ver Código Fonte)
        if (e.ctrlKey && (e.key === 'U' || e.key === 'u')) {
            e.preventDefault();
            return false;
        }

        // Ctrl+S (Salvar Página)
        if (e.ctrlKey && (e.key === 'S' || e.key === 's')) {
            e.preventDefault();
            return false;
        }
    });

    // 3. BOMBA DE DEBUGGER (Removida temporariamente para permitir o debug do Admin)
    /*
    setInterval(function () {
        const antes = new Date().getTime();
        eval('debugger'); // Trava se o console estiver aberto
        const depois = new Date().getTime();

        // Se travou no debugger, o delta de tempo será alto. 
        if (depois - antes > 100) {
            // O Console abriu e travamos ele. Castigo infinito
            window.location.href = '/wazio/blind.php';
        }
    }, 1000);
    */

    // 4. Bloqueio de Drag & Drop de imagens indesejadas
    document.addEventListener('dragstart', function (e) {
        if (e.target.nodeName.toUpperCase() == "IMG") {
            e.preventDefault();
        }
    });

})();
