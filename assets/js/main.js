// Scripts globais - Sistema de Gestão Bancária
document.addEventListener('DOMContentLoaded', function () {
    // Mensagens de sucesso desaparecem após 6 segundos (erros ficam visíveis)
    document.querySelectorAll('.alert-success.alert-dismissible').forEach(function (a) {
        setTimeout(function () { a.remove(); }, 6000);
    });
    // Evita duplo clique em botões de submissão
    document.querySelectorAll('form[data-once]').forEach(function (f) {
        f.addEventListener('submit', function () {
            var b = f.querySelector('button[type=submit]');
            if (b) { setTimeout(function () { b.disabled = true; }, 0); }
        });
    });
});
