<?php
if (empty($_SESSION['autenticado'])) {
    return;
}

if (empty($_SESSION['svxlink_update_csrf'])) {
    $_SESSION['svxlink_update_csrf'] = bin2hex(random_bytes(32));
}

$svxUpdateCsrf = $_SESSION['svxlink_update_csrf'];
?>

<div class="card shadow-sm border-0 mb-4" id="svxUpdateCard">
    <div class="card-body p-4">
        <div class="d-flex justify-content-between align-items-start gap-3 flex-wrap">
            <div>
                <h5 class="mb-1">📡 Actualización de SvxLink</h5>
                <p class="text-muted mb-1">
                    AUROXLINK puede buscar, compilar, probar y activar la última
                    release oficial de SvxLink.
                </p>
                <small class="text-muted">
                    SvxLink es un proyecto independiente de
                    <a href="https://github.com/sm0svx" target="_blank" rel="noopener">SM0SVX</a>.
                    AUROXLINK solo gestiona el proceso de actualización segura.
                </small>
            </div>

            <button
                type="button"
                class="btn btn-outline-primary px-4"
                id="svxUpdateStartBtn"
            >
                🔄 Actualizar SvxLink
            </button>
        </div>

        <div class="row g-3 mt-2">
            <div class="col-md-4">
                <div class="border rounded p-3 h-100">
                    <div class="small text-muted">Paquete de la distribución</div>
                    <div class="fw-semibold" id="svxAptVersion">Consultando...</div>
                </div>
            </div>
            <div class="col-md-4">
                <div class="border rounded p-3 h-100">
                    <div class="small text-muted">Versión AUROXLINK gestionada</div>
                    <div class="fw-semibold" id="svxManagedVersion">Consultando...</div>
                </div>
            </div>
            <div class="col-md-4">
                <div class="border rounded p-3 h-100">
                    <div class="small text-muted">Última versión encontrada</div>
                    <div class="fw-semibold" id="svxLatestVersion">Aún no consultada</div>
                </div>
            </div>
        </div>

        <div class="mt-4">
            <div class="d-flex justify-content-between align-items-center mb-2 gap-2 flex-wrap">
                <div>
                    <span class="badge bg-secondary" id="svxUpdateBadge">Listo</span>
                    <span class="ms-2 fw-semibold" id="svxUpdateMessage">
                        Listo para comprobar y actualizar SvxLink.
                    </span>
                </div>
                <span class="small text-muted" id="svxUpdatePercent">0%</span>
            </div>

            <div
                class="progress"
                role="progressbar"
                aria-label="Progreso actualización SvxLink"
                style="height: 18px;"
            >
                <div
                    class="progress-bar progress-bar-striped"
                    id="svxUpdateProgress"
                    style="width: 0%"
                >
                </div>
            </div>

            <div class="small text-muted mt-2" id="svxUpdateHint">
                La compilación puede tardar varios minutos. Puedes recargar
                esta página: el estado continuará guardado en el servidor.
            </div>
        </div>

        <div class="mt-3">
            <button
                class="btn btn-sm btn-outline-secondary"
                type="button"
                data-bs-toggle="collapse"
                data-bs-target="#svxUpdateLogCollapse"
                aria-expanded="false"
                aria-controls="svxUpdateLogCollapse"
            >
                🧾 Ver log técnico
            </button>

            <div class="collapse mt-2" id="svxUpdateLogCollapse">
                <pre
                    id="svxUpdateLog"
                    class="bg-dark text-light rounded p-3 mb-0"
                    style="max-height: 320px; overflow:auto; white-space:pre-wrap;"
                >Sin actividad todavía.</pre>
            </div>
        </div>
    </div>
</div>

<script>
(() => {
    const apiUrl = 'svxlink_update_api.php';
    const csrf = <?= json_encode($svxUpdateCsrf) ?>;

    const startBtn = document.getElementById('svxUpdateStartBtn');
    const progress = document.getElementById('svxUpdateProgress');
    const percent = document.getElementById('svxUpdatePercent');
    const message = document.getElementById('svxUpdateMessage');
    const badge = document.getElementById('svxUpdateBadge');
    const logBox = document.getElementById('svxUpdateLog');
    const aptVersion = document.getElementById('svxAptVersion');
    const managedVersion = document.getElementById('svxManagedVersion');
    const latestVersion = document.getElementById('svxLatestVersion');

    let timer = null;

    function badgeState(status, phase) {
        badge.className = 'badge';

        if (status === 'running') {
            badge.classList.add('bg-warning', 'text-dark');
            badge.textContent = phase === 'compile' ? 'Compilando' : 'En proceso';
            return;
        }

        if (status === 'completed') {
            badge.classList.add('bg-success');
            badge.textContent = 'Completado';
            return;
        }

        if (status === 'failed') {
            badge.classList.add('bg-danger');
            badge.textContent = phase === 'failed_rollback'
                ? 'Rollback aplicado'
                : 'Error';
            return;
        }

        badge.classList.add('bg-secondary');
        badge.textContent = 'Listo';
    }

    function render(data) {
        const state = data.state || {};
        const versions = data.versions || {};
        const node = data.node || {};
        const value = Number(state.progress || 0);

        progress.style.width = `${value}%`;
        progress.textContent = '';
        percent.textContent = `${value}%`;
        message.textContent = state.message || 'Sin estado disponible.';

        progress.classList.toggle(
            'progress-bar-animated',
            state.status === 'running'
        );

        progress.classList.remove('bg-success', 'bg-danger');
        if (state.status === 'completed') progress.classList.add('bg-success');
        if (state.status === 'failed') progress.classList.add('bg-danger');

        badgeState(state.status, state.phase);

        aptVersion.textContent = versions.apt || 'No instalado';

        if (versions.managed) {
            managedVersion.textContent =
                versions.managed +
                (versions.managed_active ? ' — activa' : ' — no activa');
        } else {
            managedVersion.textContent = 'No instalada';
        }

        latestVersion.textContent =
            state.latest_version || 'Aún no consultada';

        const nodeReady = node.ready === true;

        startBtn.disabled =
            state.status === 'running' ||
            !nodeReady;

        if (state.status === 'running') {
            startBtn.innerHTML = '⏳ Actualización en curso';
            startBtn.title = '';
        } else if (!nodeReady) {
            startBtn.innerHTML = '⚠️ SvxLink no operativo';
            startBtn.title =
                node.reason ||
                'Configura y deja funcionando SvxLink antes de actualizar.';
        } else {
            startBtn.innerHTML = '🔄 Actualizar SvxLink';
            startBtn.title = '';
        }

        if (data.log && data.log.trim() !== '') {
            const wasAtBottom =
                logBox.scrollTop + logBox.clientHeight >= logBox.scrollHeight - 30;
            logBox.textContent = data.log;
            if (wasAtBottom) {
                logBox.scrollTop = logBox.scrollHeight;
            }
        }

        if (state.status === 'running') {
            schedulePoll(2000);
        } else {
            stopPoll();
        }
    }

    async function loadStatus() {
        try {
            const response = await fetch(
                `${apiUrl}?action=status&_=${Date.now()}`,
                {cache: 'no-store'}
            );

            const data = await response.json();

            if (!response.ok || !data.ok) {
                throw new Error(data.error || 'No se pudo leer el estado.');
            }

            render(data);
        } catch (error) {
            badge.className = 'badge bg-danger';
            badge.textContent = 'Error';
            message.textContent = error.message;
        }
    }

    function schedulePoll(ms) {
        clearTimeout(timer);
        timer = setTimeout(loadStatus, ms);
    }

    function stopPoll() {
        clearTimeout(timer);
        timer = null;
    }

    startBtn.addEventListener('click', async () => {
        const confirmed = window.confirm(
            'AUROXLINK buscará la última release oficial de SvxLink y, si corresponde, la compilará y probará antes de activarla.\n\n' +
            'El proceso puede tardar alrededor de 20 a 40 minutos dependiendo del servidor. Durante la prueba final SvxLink se detendrá temporalmente.\n\n' +
            'Si algo falla, el instalador intentará restaurar automáticamente la versión anterior.\n\n' +
            '¿Deseas continuar?'
        );

        if (!confirmed) return;

        startBtn.disabled = true;
        startBtn.innerHTML = '⏳ Iniciando...';
        message.textContent = 'Solicitando inicio de actualización...';

        try {
            const body = new URLSearchParams();
            body.set('action', 'start');
            body.set('csrf', csrf);

            const response = await fetch(apiUrl, {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/x-www-form-urlencoded;charset=UTF-8'
                },
                body: body.toString()
            });

            const data = await response.json();

            if (!response.ok || !data.ok) {
                throw new Error(data.error || 'No se pudo iniciar.');
            }

            schedulePoll(500);
        } catch (error) {
            startBtn.disabled = false;
            startBtn.innerHTML = '🔄 Actualizar SvxLink';
            badge.className = 'badge bg-danger';
            badge.textContent = 'Error';
            message.textContent = error.message;
        }
    });

    loadStatus();
})();
</script>
