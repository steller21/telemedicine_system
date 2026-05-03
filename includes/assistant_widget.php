<?php

if (!function_exists('mediconnect_render_assistant')) {
    function mediconnect_render_assistant(string $chatbotPath): void
    {
        static $rendered = false;
        if ($rendered) {
            return;
        }
        $rendered = true;
        $safePath = htmlspecialchars($chatbotPath . '?embed=1', ENT_QUOTES, 'UTF-8');
        ?>
<style>
.assistant-shell {
    position: fixed;
    right: 24px;
    bottom: 24px;
    z-index: 250;
    display: flex;
    flex-direction: column;
    align-items: flex-end;
    gap: 14px;
    pointer-events: none;
}
.assistant-panel {
    width: min(380px, calc(100vw - 32px));
    height: min(620px, calc(100vh - 110px));
    background: rgba(245,240,232,0.98);
    border: 1px solid rgba(255,255,255,0.18);
    border-radius: 22px;
    overflow: hidden;
    box-shadow: 0 24px 70px rgba(0,0,0,0.38);
    backdrop-filter: blur(18px);
    opacity: 0;
    transform: translateY(18px) scale(0.96);
    transform-origin: bottom right;
    pointer-events: none;
    transition: opacity 0.25s ease, transform 0.25s ease;
}
.assistant-shell.open .assistant-panel {
    opacity: 1;
    transform: translateY(0) scale(1);
    pointer-events: auto;
}
.assistant-frame {
    width: 100%;
    height: 100%;
    border: 0;
    background: #fff;
    pointer-events: auto;
}
.assistant-toggle {
    width: 68px;
    height: 68px;
    border: none;
    border-radius: 999px;
    cursor: pointer;
    display: inline-flex;
    align-items: center;
    justify-content: center;
    background: linear-gradient(135deg, #0EB8A0 0%, #5fe0cf 100%);
    color: #0B1526;
    box-shadow: 0 16px 40px rgba(14,184,160,0.38);
    transition: transform 0.2s ease, box-shadow 0.2s ease;
    pointer-events: auto;
}
.assistant-toggle:hover {
    transform: translateY(-3px) scale(1.02);
    box-shadow: 0 22px 46px rgba(14,184,160,0.45);
}
.assistant-toggle svg {
    width: 30px;
    height: 30px;
}
.assistant-badge {
    position: absolute;
    right: 76px;
    bottom: 10px;
    background: rgba(11,21,38,0.9);
    color: #fff;
    padding: 10px 14px;
    border-radius: 999px;
    font-size: 0.82rem;
    letter-spacing: 0.02em;
    white-space: nowrap;
    border: 1px solid rgba(255,255,255,0.08);
    box-shadow: 0 12px 30px rgba(0,0,0,0.24);
    opacity: 0;
    transform: translateX(10px);
    transition: opacity 0.2s ease, transform 0.2s ease;
    pointer-events: none;
}
.assistant-shell:hover .assistant-badge,
.assistant-shell.show-badge .assistant-badge {
    opacity: 1;
    transform: translateX(0);
}
@media (max-width: 600px) {
    .assistant-shell {
        right: 16px;
        bottom: 16px;
        left: auto;
        align-items: flex-end;
    }
    .assistant-panel {
        width: calc(100vw - 32px);
        height: min(72vh, 520px);
        transform-origin: bottom center;
    }
    .assistant-toggle {
        align-self: flex-end;
        width: 62px;
        height: 62px;
    }
    .assistant-badge {
        right: 72px;
        max-width: calc(100vw - 120px);
        overflow: hidden;
        text-overflow: ellipsis;
    }
}
</style>

<div class="assistant-shell" id="assistantShell">
    <div class="assistant-panel" id="assistantPanel" aria-hidden="true">
        <iframe class="assistant-frame" src="<?php echo $safePath; ?>" title="AI Health Assistant" loading="lazy"></iframe>
    </div>
    <div class="assistant-badge" id="assistantBadge">Ask the AI Health Assistant</div>
    <button type="button" class="assistant-toggle" id="assistantToggle" aria-controls="assistantPanel" aria-expanded="false" aria-label="Open AI assistant">
        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
            <path d="M12 3l1.2 2.8L16 7l-2.8 1.2L12 11l-1.2-2.8L8 7l2.8-1.2L12 3z"></path>
            <path d="M5 13.5a2.5 2.5 0 0 1 2.5-2.5h9A2.5 2.5 0 0 1 19 13.5v3A2.5 2.5 0 0 1 16.5 19h-9A2.5 2.5 0 0 1 5 16.5v-3z"></path>
            <path d="M9 19v2"></path>
            <path d="M15 19v2"></path>
            <path d="M9 14h.01"></path>
            <path d="M15 14h.01"></path>
        </svg>
    </button>
</div>

<script>
(() => {
    const assistantShell = document.getElementById('assistantShell');
    const assistantToggle = document.getElementById('assistantToggle');
    const assistantPanel = document.getElementById('assistantPanel');

    if (!assistantShell || !assistantToggle || !assistantPanel) {
        return;
    }

    let assistantBadgeTimeout;

    function setAssistantState(isOpen) {
        assistantShell.classList.toggle('open', isOpen);
        assistantToggle.setAttribute('aria-expanded', String(isOpen));
        assistantPanel.setAttribute('aria-hidden', String(!isOpen));
        assistantToggle.setAttribute('aria-label', isOpen ? 'Close AI assistant' : 'Open AI assistant');
        if (isOpen) {
            assistantShell.classList.remove('show-badge');
        }
    }

    function pulseAssistantBadge() {
        if (assistantShell.classList.contains('open')) return;
        assistantShell.classList.add('show-badge');
        clearTimeout(assistantBadgeTimeout);
        assistantBadgeTimeout = setTimeout(() => {
            if (!assistantShell.matches(':hover')) {
                assistantShell.classList.remove('show-badge');
            }
        }, 7000);
    }

    assistantToggle.addEventListener('click', () => {
        const isOpen = assistantShell.classList.contains('open');
        setAssistantState(!isOpen);
    });

    document.addEventListener('click', (event) => {
        if (!assistantShell.classList.contains('open')) return;
        if (assistantShell.contains(event.target)) return;
        setAssistantState(false);
    });

    // Auto-hide system alerts after 10 seconds
    const alerts = document.querySelectorAll('.alert, .alert-error, .alert-success, .alert-warning');
    if (alerts.length > 0) {
        setTimeout(() => {
            alerts.forEach(alert => {
                alert.style.transition = 'opacity 1s ease, transform 0.5s ease';
                alert.style.opacity = '0';
                alert.style.transform = 'translateY(-10px)';
                setTimeout(() => alert.remove(), 1000);
            });
        }, 10000);

        // Clear URL parameters to prevent messages reappearing on refresh
        if (window.history.replaceState) {
            const url = new URL(window.location.href);
            url.searchParams.delete('success');
            url.searchParams.delete('error');
            url.searchParams.delete('share_success');
            window.history.replaceState({}, '', url.href);
        }
    }

    setInterval(pulseAssistantBadge, 20000);
})();
</script>
<?php
    }
}
