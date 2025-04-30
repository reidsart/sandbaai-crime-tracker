class NotificationToast {
    constructor(options) {
        this.options = Object.assign({
            title: '',
            message: '',
            type: 'info',
            duration: 5000,
            onClick: null,
            onClose: null
        }, options);

        this.element = null;
        this.timeoutId = null;
    }

    createToastElement() {
        const toast = document.createElement('div');
        toast.className = `notification-toast ${this.options.type}`;
        toast.innerHTML = `
            <div class="toast-header">
                <span class="toast-title">${this.options.title}</span>
                <button class="toast-close">&times;</button>
            </div>
            <div class="toast-body">
                <p class="toast-message">${this.options.message}</p>
            </div>
        `;

        // Add event listeners
        toast.querySelector('.toast-close').addEventListener('click', (e) => {
            e.stopPropagation();
            this.close();
        });

        if (this.options.onClick) {
            toast.addEventListener('click', () => {
                this.options.onClick();
                this.close();
            });
        }

        return toast;
    }

    show() {
        if (this.element) {
            this.close();
        }

        this.element = this.createToastElement();
        document.body.appendChild(this.element);

        // Trigger animation
        requestAnimationFrame(() => {
            this.element.classList.add('show');
        });

        // Auto close after duration
        if (this.options.duration > 0) {
            this.timeoutId = setTimeout(() => {
                this.close();
            }, this.options.duration);
        }
    }

    close() {
        if (!this.element) return;

        clearTimeout(this.timeoutId);
        this.element.classList.remove('show');

        // Remove element after animation
        this.element.addEventListener('transitionend', () => {
            if (this.element && this.element.parentNode) {
                this.element.parentNode.removeChild(this.element);
                this.element = null;
                if (this.options.onClose) {
                    this.options.onClose();
                }
            }
        });
    }
}