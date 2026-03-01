(function () {
    'use strict';

    window.toggleTokenVisibility = function toggleTokenVisibility() {
        const field = document.getElementById('ypm_github_token');
        if (!field) {
            return false;
        }
        field.type = field.type === 'password' ? 'text' : 'password';
        return false;
    };

    window.setMainDrawerPanel = function setMainDrawerPanel(panel) {
        const drawer = document.getElementById('ypm-main-drawer');
        const installTab = document.getElementById('ypm-main-drawer-toggle-install');
        const tokenTab = document.getElementById('ypm-main-drawer-toggle-token');
        if (!drawer || !installTab || !tokenTab) {
            return false;
        }

        const isToken = panel === 'token';
        drawer.classList.toggle('is-main-open', !isToken);
        drawer.classList.toggle('is-token-open', isToken);
        installTab.classList.toggle('is-active', !isToken);
        tokenTab.classList.toggle('is-active', isToken);
        installTab.setAttribute('aria-expanded', !isToken ? 'true' : 'false');
        tokenTab.setAttribute('aria-expanded', isToken ? 'true' : 'false');
        return false;
    };

    window.openAssociateRepoModal = function openAssociateRepoModal(slug, name, repoUrl) {
        const modal = document.getElementById('ypm-associate-modal');
        const slugField = document.getElementById('ypm-associate-plugin');
        const nameField = document.getElementById('ypm-associate-plugin-name');
        const urlField = document.getElementById('ypm-associate-repo-url');
        if (!modal || !slugField || !nameField || !urlField) {
            return false;
        }

        slugField.value = slug || '';
        nameField.textContent = name || slug || '';
        urlField.value = repoUrl || '';
        modal.classList.add('is-open');
        modal.setAttribute('aria-hidden', 'false');
        document.body.classList.add('ypm-modal-open');
        setTimeout(function () {
            urlField.focus();
        }, 30);
        return false;
    };

    window.closeAssociateRepoModal = function closeAssociateRepoModal() {
        const modal = document.getElementById('ypm-associate-modal');
        if (!modal) {
            return false;
        }

        modal.classList.remove('is-open');
        modal.setAttribute('aria-hidden', 'true');
        document.body.classList.remove('ypm-modal-open');
        return false;
    };

    function bindAdminEvents() {
        document.addEventListener('click', function (event) {
            const tokenToggle = event.target.closest('.ypm-token-visibility-toggle');
            if (tokenToggle) {
                event.preventDefault();
                window.toggleTokenVisibility();
                return;
            }

            const drawerToggle = event.target.closest('.ypm-drawer-panel-toggle');
            if (drawerToggle) {
                event.preventDefault();
                window.setMainDrawerPanel(drawerToggle.getAttribute('data-panel') || 'main');
                return;
            }

            const openAssociate = event.target.closest('.ypm-open-associate');
            if (openAssociate) {
                event.preventDefault();
                window.openAssociateRepoModal(
                    openAssociate.dataset.pluginSlug || '',
                    openAssociate.dataset.pluginName || '',
                    openAssociate.dataset.repoUrl || ''
                );
                return;
            }

            const closeModal = event.target.closest('.ypm-modal-close-action');
            if (closeModal) {
                event.preventDefault();
                window.closeAssociateRepoModal();
                return;
            }

            const deleteConfirm = event.target.closest('.ypm-delete-confirm');
            if (deleteConfirm) {
                const message = deleteConfirm.getAttribute('data-confirm-message') || '';
                if (message !== '' && !window.confirm(message)) {
                    event.preventDefault();
                }
            }
        });
    }

    document.addEventListener('keydown', function (event) {
        if (event.key === 'Escape') {
            window.closeAssociateRepoModal();
        }
    });

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', bindAdminEvents);
    } else {
        bindAdminEvents();
    }
})();
