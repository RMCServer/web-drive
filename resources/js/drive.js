/**
 * Client-side behaviour of the Drive browser: view modes, selection, the
 * context menu, the file viewer, drag & drop moves and uploads.
 *
 * The browser only ever calls the existing, authorized Drive endpoints;
 * every rule (ownership, nesting, upload limits) is enforced server side.
 */

const VIEW_STORAGE_KEY = 'drive.view';
const MAX_PARALLEL_UPLOADS = 3;
const ROOT_TARGET = 'root';

let nextUploadId = 1;

function readViewMode() {
    try {
        return localStorage.getItem(VIEW_STORAGE_KEY) === 'list' ? 'list' : 'grid';
    } catch {
        return 'grid';
    }
}

function writeViewMode(view) {
    try {
        localStorage.setItem(VIEW_STORAGE_KEY, view);
    } catch {
        // Storage can be unavailable (private mode, blocked site data); the default view is fine then.
    }
}

function isTypingTarget(element) {
    return element instanceof HTMLElement && (element.isContentEditable || ['INPUT', 'TEXTAREA', 'SELECT'].includes(element.tagName));
}

function hasFiles(event) {
    return Array.from(event.dataTransfer?.types ?? []).includes('Files');
}

function firstErrorMessage(data) {
    const errors = Object.values(data?.errors ?? {}).flat();

    return errors[0] ?? data?.message ?? null;
}

export default function driveBrowser(config) {
    return {
        items: config.items,
        folderUuid: config.folderUuid,
        view: readViewMode(),
        search: '',

        selected: [],
        selectionAnchor: null,

        menu: { open: false, x: 0, y: 0, item: null },
        viewer: { open: false, item: null, zoomed: false },
        text: { loading: false, failed: false, content: '', truncated: false, controller: null },
        failedThumbnails: {},

        dragged: [],
        dropTarget: null,
        fileDragDepth: 0,

        uploads: [],
        activeUploads: 0,

        renameTarget: null,
        renameName: '',
        moveTarget: null,
        moveDestination: '',

        init() {
            this.$watch('view', (view) => writeViewMode(view));
        },

        destroy() {
            this.unlockScroll();
            this.text.controller?.abort();
        },

        // Listing ------------------------------------------------------------

        get visibleItems() {
            const query = this.search.trim().toLocaleLowerCase();

            return query === '' ? this.items : this.items.filter((item) => item.name.toLocaleLowerCase().includes(query));
        },

        get visibleFolders() {
            return this.visibleItems.filter((item) => item.isFolder);
        },

        get visibleFiles() {
            return this.visibleItems.filter((item) => ! item.isFolder);
        },

        thumbnailUrl(item) {
            return item.urls.thumbnail && ! this.failedThumbnails[item.uuid] ? item.urls.thumbnail : null;
        },

        imagePreviewUrl(item) {
            if (item.previewType !== 'image') {
                return null;
            }

            return this.thumbnailUrl(item) ?? item.urls.preview;
        },

        markThumbnailFailed(item) {
            this.failedThumbnails[item.uuid] = true;
        },

        formatDate(isoDate) {
            return isoDate ? new Date(isoDate).toLocaleString() : '';
        },

        findItem(uuid) {
            return this.items.find((item) => item.uuid === uuid);
        },

        /**
         * Reload the current folder's direct children without a page load.
         */
        async refresh() {
            const response = await this.request(config.urls.listing);

            if (! response.ok) {
                return;
            }

            this.items = response.data.data;
            this.selected = this.selected.filter((uuid) => this.findItem(uuid));

            if (this.viewer.open) {
                const current = this.findItem(this.viewer.item?.uuid);

                current ? (this.viewer.item = current) : this.closeViewer();
            }
        },

        navigate(url) {
            window.Livewire?.navigate ? window.Livewire.navigate(url) : window.location.assign(url);
        },

        open(item) {
            this.closeMenu();

            item.isFolder ? this.navigate(item.urls.open) : this.openViewer(item);
        },

        download(item) {
            if (item.urls.download) {
                window.location.assign(item.urls.download);
            }
        },

        // Selection ----------------------------------------------------------

        isSelected(item) {
            return this.selected.includes(item.uuid);
        },

        get selectionLabel() {
            return this.message('selected', { count: this.selected.length });
        },

        select(item, event) {
            if (event.metaKey || event.ctrlKey) {
                this.selected = this.isSelected(item)
                    ? this.selected.filter((uuid) => uuid !== item.uuid)
                    : [...this.selected, item.uuid];
                this.selectionAnchor = item.uuid;

                return;
            }

            const uuids = this.visibleItems.map((visibleItem) => visibleItem.uuid);
            const anchorIndex = uuids.indexOf(this.selectionAnchor);

            if (event.shiftKey && anchorIndex !== -1) {
                const itemIndex = uuids.indexOf(item.uuid);

                this.selected = uuids.slice(Math.min(anchorIndex, itemIndex), Math.max(anchorIndex, itemIndex) + 1);

                return;
            }

            this.selected = [item.uuid];
            this.selectionAnchor = item.uuid;
        },

        clearSelection() {
            this.selected = [];
            this.selectionAnchor = null;
        },

        onBackgroundClick(event) {
            if (! event.target.closest('[data-drive-item], [data-drive-keep-selection]')) {
                this.clearSelection();
            }
        },

        // Keyboard -----------------------------------------------------------

        onKeydown(event) {
            // Let open Flux modals (rename, move, new folder) handle their own keys.
            if (document.querySelector('dialog[open]')) {
                return;
            }

            if (this.menu.open && event.key === 'Escape') {
                this.closeMenu();

                return;
            }

            if (this.viewer.open) {
                if (event.key === 'Escape') {
                    this.closeViewer();
                } else if (event.key === 'ArrowLeft') {
                    this.showAdjacentFile(-1);
                } else if (event.key === 'ArrowRight') {
                    this.showAdjacentFile(1);
                }

                return;
            }

            if (isTypingTarget(event.target)) {
                return;
            }

            if (event.key === 'Escape') {
                this.clearSelection();
            } else if (event.key.toLowerCase() === 'a' && (event.metaKey || event.ctrlKey)) {
                event.preventDefault();
                this.selected = this.visibleItems.map((item) => item.uuid);
            }
        },

        // Context menu -------------------------------------------------------

        openMenu(item, event) {
            if (! this.isSelected(item)) {
                this.selected = [item.uuid];
                this.selectionAnchor = item.uuid;
            }

            this.showMenuAt(item, event.clientX, event.clientY);
        },

        openMenuFromButton(item, event) {
            const rect = event.currentTarget.getBoundingClientRect();

            if (this.menu.open && this.menu.item?.uuid === item.uuid) {
                this.closeMenu();

                return;
            }

            if (! this.viewer.open) {
                this.selected = [item.uuid];
                this.selectionAnchor = item.uuid;
            }

            this.showMenuAt(item, rect.right, rect.bottom + 4, true);
        },

        showMenuAt(item, x, y, alignEnd = false) {
            this.menu = { open: true, x, y, item };

            this.$nextTick(() => {
                const { width, height } = this.$refs.menu.getBoundingClientRect();
                const left = alignEnd ? x - width : x;

                this.menu.x = Math.max(8, Math.min(left, window.innerWidth - width - 8));
                this.menu.y = Math.max(8, y + height > window.innerHeight - 8 ? y - height : y);
                this.$refs.menu.querySelector('[role="menuitem"]')?.focus({ preventScroll: true });
            });
        },

        closeMenu() {
            this.menu.open = false;
        },

        runMenuAction(action) {
            const item = this.menu.item;

            this.closeMenu();

            if (! item) {
                return;
            }

            if (['rename', 'move', 'trash'].includes(action)) {
                this.closeViewer();
            }

            ({
                open: () => this.open(item),
                download: () => this.download(item),
                rename: () => this.showRename(item),
                move: () => this.showMove(item),
                trash: () => this.trash(item),
            })[action]?.();
        },

        showRename(item) {
            this.renameTarget = item;
            this.renameName = item.name;
            window.Flux.modal('rename-item').show();

            // Select the name without its extension, like a desktop file manager.
            setTimeout(() => {
                const input = this.$refs.renameInput?.querySelector('input') ?? this.$refs.renameInput;
                const extensionStart = item.isFolder ? -1 : item.name.lastIndexOf('.');

                input?.focus();
                input?.setSelectionRange(0, extensionStart > 0 ? extensionStart : item.name.length);
            }, 50);
        },

        showMove(item) {
            this.moveTarget = item;
            this.moveDestination = this.folderUuid ?? '';
            window.Flux.modal('move-item').show();
        },

        trash(item) {
            this.$refs.trashForm.action = item.urls.destroy;
            this.$refs.trashForm.submit();
        },

        // Viewer -------------------------------------------------------------

        get viewerFiles() {
            return this.visibleFiles;
        },

        get viewerPositionLabel() {
            const index = this.viewerFiles.findIndex((item) => item.uuid === this.viewer.item?.uuid);

            return index === -1 ? '' : `${index + 1} / ${this.viewerFiles.length}`;
        },

        openViewer(item) {
            this.viewer.item = item;
            this.viewer.zoomed = false;
            this.selected = [item.uuid];
            this.selectionAnchor = item.uuid;

            if (! this.viewer.open) {
                this.viewer.open = true;
                this.lockScroll();
                this.$nextTick(() => this.$refs.viewer.focus({ preventScroll: true }));
            }

            this.loadTextPreview(item);
        },

        closeViewer() {
            if (! this.viewer.open) {
                return;
            }

            this.viewer.open = false;
            this.viewer.item = null;
            this.text.controller?.abort();
            this.unlockScroll();
        },

        showAdjacentFile(offset) {
            const files = this.viewerFiles;
            const index = files.findIndex((item) => item.uuid === this.viewer.item?.uuid);

            if (files.length > 1 && index !== -1) {
                this.openViewer(files[(index + offset + files.length) % files.length]);
            }
        },

        async loadTextPreview(item) {
            this.text.controller?.abort();
            this.text = { loading: false, failed: false, content: '', truncated: false, controller: null };

            if (item.previewType !== 'text') {
                return;
            }

            const controller = new AbortController();

            this.text.controller = controller;
            this.text.loading = true;

            try {
                const response = await fetch(item.urls.preview, { signal: controller.signal, headers: { Accept: 'text/plain' } });

                if (! response.ok) {
                    throw new Error(`Preview request failed with status ${response.status}`);
                }

                this.text.content = await response.text();
                this.text.truncated = response.headers.get('X-Drive-Preview-Truncated') === '1';
            } catch (error) {
                if (error.name === 'AbortError') {
                    return;
                }

                this.text.failed = true;
            }

            this.text.loading = false;
        },

        lockScroll() {
            document.documentElement.classList.add('overflow-hidden');
        },

        unlockScroll() {
            document.documentElement.classList.remove('overflow-hidden');
        },

        // Moving items with drag & drop --------------------------------------

        isBeingDragged(item) {
            return this.dragged.includes(item.uuid);
        },

        onItemDragStart(item, event) {
            if (! this.isSelected(item)) {
                this.selected = [item.uuid];
                this.selectionAnchor = item.uuid;
            }

            this.dragged = [...this.selected];
            this.closeMenu();

            event.dataTransfer.effectAllowed = 'move';
            event.dataTransfer.setData('application/x-drive-items', JSON.stringify(this.dragged));
            event.dataTransfer.setData('text/plain', item.name);
        },

        onItemDragEnd() {
            this.dragged = [];
            this.dropTarget = null;
        },

        /**
         * Only obviously pointless drops are ignored here (onto a dragged item
         * or into the folder the items are already in); the server validates
         * every move, including moves into a subfolder of a dragged folder.
         */
        canDropInto(destinationUuid) {
            if (this.dragged.length > 0) {
                return ! this.dragged.includes(destinationUuid) && destinationUuid !== this.folderUuid;
            }

            return true;
        },

        onTargetDragOver(destinationUuid, event) {
            const isFileDrag = this.dragged.length === 0 && hasFiles(event);

            if ((this.dragged.length === 0 && ! isFileDrag) || ! this.canDropInto(destinationUuid)) {
                return;
            }

            event.preventDefault();
            event.stopPropagation();
            event.dataTransfer.dropEffect = isFileDrag ? 'copy' : 'move';
            this.dropTarget = destinationUuid ?? ROOT_TARGET;
        },

        onTargetDragLeave(destinationUuid, event) {
            if (this.dropTarget === (destinationUuid ?? ROOT_TARGET) && ! event.currentTarget.contains(event.relatedTarget)) {
                this.dropTarget = null;
            }
        },

        onTargetDrop(destinationUuid, event) {
            if (this.dropTarget !== (destinationUuid ?? ROOT_TARGET)) {
                return;
            }

            event.preventDefault();
            event.stopPropagation();

            const dragged = this.dragged;

            this.dropTarget = null;
            this.fileDragDepth = 0;
            this.dragged = [];

            dragged.length > 0
                ? this.moveItems(dragged, destinationUuid)
                : this.uploadFromDataTransfer(event.dataTransfer, destinationUuid);
        },

        async moveItems(uuids, destinationUuid) {
            const items = uuids.map((uuid) => this.findItem(uuid)).filter(Boolean);
            const failures = [];
            let moved = 0;

            for (const item of items) {
                const response = await this.request(item.urls.move, 'PATCH', { destination: destinationUuid });

                if (response.ok) {
                    moved++;
                } else {
                    failures.push(firstErrorMessage(response.data) ?? this.message('moveFailed', { name: item.name }));
                }
            }

            if (moved > 0) {
                this.toast(moved === 1 && items.length === 1 ? this.message('moved', { name: items[0].name }) : this.message('movedMany', { count: moved }));
                await this.refresh();
            }

            [...new Set(failures)].forEach((failure) => this.toast(failure, 'danger'));
        },

        // Uploading with drag & drop -----------------------------------------

        get isDraggingFiles() {
            return this.fileDragDepth > 0;
        },

        onDragEnter(event) {
            if (this.dragged.length === 0 && hasFiles(event)) {
                event.preventDefault();
                this.fileDragDepth++;
            }
        },

        onDragOver(event) {
            if (this.dragged.length === 0 && hasFiles(event)) {
                event.preventDefault();
                event.dataTransfer.dropEffect = 'copy';
            }
        },

        onDragLeave(event) {
            if (this.dragged.length === 0 && hasFiles(event)) {
                this.fileDragDepth = Math.max(0, this.fileDragDepth - 1);
            }
        },

        onDrop(event) {
            if (this.dragged.length === 0 && hasFiles(event)) {
                event.preventDefault();
                this.fileDragDepth = 0;
                this.uploadFromDataTransfer(event.dataTransfer, this.folderUuid);
            }
        },

        // Keep the browser from opening files dropped next to the Drive area.
        onWindowDragOver(event) {
            if (hasFiles(event)) {
                event.preventDefault();
            }
        },

        onWindowDrop(event) {
            if (hasFiles(event)) {
                event.preventDefault();
                this.fileDragDepth = 0;
            }
        },

        uploadFromDataTransfer(dataTransfer, parentUuid) {
            const entries = Array.from(dataTransfer.items ?? []).filter((entry) => entry.kind === 'file');
            let files = Array.from(dataTransfer.files ?? []);

            // Dropped folders show up as files that cannot be read; skip them.
            if (entries.length > 0 && typeof entries[0].webkitGetAsEntry === 'function') {
                const directories = entries.filter((entry) => entry.webkitGetAsEntry()?.isDirectory);

                if (directories.length > 0) {
                    this.toast(this.message('foldersNotSupported'), 'warning');
                }

                files = entries
                    .filter((entry) => ! entry.webkitGetAsEntry()?.isDirectory)
                    .map((entry) => entry.getAsFile())
                    .filter(Boolean);
            }

            this.uploadFiles(files, parentUuid);
        },

        get hasPendingUploads() {
            return this.uploads.some((upload) => ['queued', 'uploading'].includes(upload.status));
        },

        get uploadSummary() {
            const done = this.uploads.filter((upload) => upload.status === 'done').length;
            const failed = this.uploads.filter((upload) => upload.status === 'failed').length;

            if (this.hasPendingUploads) {
                return this.message('uploading', { current: Math.min(done + failed + 1, this.uploads.length), total: this.uploads.length });
            }

            return failed > 0 ? this.message('uploadsFailed', { done, failed }) : this.message('uploadsComplete', { count: done });
        },

        uploadFiles(files, parentUuid) {
            for (const file of files) {
                const isTooLarge = file.size > config.maxUploadBytes;

                this.uploads.push({
                    id: nextUploadId++,
                    name: file.name,
                    file: isTooLarge ? null : file,
                    parentUuid,
                    progress: 0,
                    status: isTooLarge ? 'failed' : 'queued',
                    error: isTooLarge ? this.message('tooLarge') : null,
                });
            }

            this.pumpUploads();
        },

        pumpUploads() {
            while (this.activeUploads < MAX_PARALLEL_UPLOADS) {
                const upload = this.uploads.find((candidate) => candidate.status === 'queued');

                if (! upload) {
                    break;
                }

                this.activeUploads++;
                this.sendUpload(upload).finally(() => {
                    this.activeUploads--;

                    if (! this.hasPendingUploads) {
                        this.finishUploads();
                    } else {
                        this.pumpUploads();
                    }
                });
            }
        },

        finishUploads() {
            const uploaded = this.uploads.filter((upload) => upload.status === 'done' && ! upload.announced);

            if (uploaded.length === 0) {
                return;
            }

            uploaded.forEach((upload) => (upload.announced = true));
            this.toast(this.message('uploaded', { count: uploaded.length }));
            this.refresh();
        },

        /**
         * Upload one file through the regular upload endpoint, reporting progress.
         */
        sendUpload(upload) {
            upload.status = 'uploading';

            return new Promise((resolve) => {
                const xhr = new XMLHttpRequest();
                const form = new FormData();

                form.append('file', upload.file);

                if (upload.parentUuid) {
                    form.append('parent', upload.parentUuid);
                }

                xhr.open('POST', config.urls.upload);
                xhr.setRequestHeader('Accept', 'application/json');
                xhr.setRequestHeader('X-Requested-With', 'XMLHttpRequest');
                xhr.setRequestHeader('X-CSRF-TOKEN', config.csrfToken);

                xhr.upload.addEventListener('progress', (event) => {
                    if (event.lengthComputable) {
                        upload.progress = Math.round((event.loaded / event.total) * 100);
                    }
                });

                xhr.addEventListener('load', () => {
                    let data = null;

                    try {
                        data = JSON.parse(xhr.responseText);
                    } catch {
                        // Non-JSON responses (e.g. a proxy's 413 page) fall through to the generic message.
                    }

                    if (xhr.status >= 200 && xhr.status < 300) {
                        upload.status = 'done';
                        upload.progress = 100;
                    } else {
                        upload.status = 'failed';
                        upload.error = this.errorForStatus(xhr.status, data, 'uploadFailed');
                    }

                    upload.file = null;
                    resolve();
                });

                xhr.addEventListener('error', () => {
                    upload.status = 'failed';
                    upload.error = this.message('uploadFailed');
                    upload.file = null;
                    resolve();
                });

                xhr.send(form);
            });
        },

        clearFinishedUploads() {
            this.uploads = this.uploads.filter((upload) => ['queued', 'uploading'].includes(upload.status));
        },

        // Helpers ------------------------------------------------------------

        async request(url, method = 'GET', body = null) {
            try {
                const response = await fetch(url, {
                    method,
                    cache: 'no-store',
                    headers: {
                        Accept: 'application/json',
                        'Content-Type': 'application/json',
                        'X-Requested-With': 'XMLHttpRequest',
                        'X-CSRF-TOKEN': config.csrfToken,
                    },
                    body: body === null ? null : JSON.stringify(body),
                });

                const data = await response.json().catch(() => null);

                if (! response.ok && response.status === 419) {
                    return { ok: false, status: 419, data: { message: this.message('sessionExpired') } };
                }

                return { ok: response.ok, status: response.status, data };
            } catch {
                return { ok: false, status: 0, data: { message: this.message('requestFailed') } };
            }
        },

        errorForStatus(status, data, fallback) {
            if (status === 413) {
                return this.message('tooLarge');
            }

            if (status === 419) {
                return this.message('sessionExpired');
            }

            return (status === 422 && firstErrorMessage(data)) || this.message(fallback);
        },

        message(key, replacements = {}) {
            return Object.entries(replacements).reduce(
                (message, [name, value]) => message.replaceAll(`:${name}`, value),
                config.messages[key] ?? key,
            );
        },

        toast(text, variant = 'success') {
            window.Flux?.toast({ text, variant });
        },
    };
}
