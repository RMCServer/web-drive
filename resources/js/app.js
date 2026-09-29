import driveBrowser from './drive';

document.addEventListener('alpine:init', () => {
    window.Alpine.data('driveBrowser', driveBrowser);
});
