import './bootstrap';

import Alpine from 'alpinejs';

window.Alpine = Alpine;

Alpine.start();

const themePreferenceKey = 'out-of-boredom-theme';
const themeButtons = document.querySelectorAll('[data-theme-toggle]');
const themeColorMeta = document.querySelector('meta[name="theme-color"]');

function applyTheme(theme) {
    const isDark = theme === 'dark';

    document.documentElement.dataset.theme = isDark ? 'dark' : 'light';
    document.documentElement.classList.toggle('dark', isDark);

    if (themeColorMeta) {
        themeColorMeta.content = isDark ? '#17191d' : '#f6f5f2';
    }

    themeButtons.forEach((button) => {
        const label = isDark ? 'Switch to light mode' : 'Switch to dark mode';

        button.setAttribute('aria-label', label);
        button.setAttribute('title', label);
        button.setAttribute('aria-pressed', String(isDark));
    });
}

applyTheme(document.documentElement.dataset.theme);

themeButtons.forEach((button) => {
    button.addEventListener('click', () => {
        const nextTheme = document.documentElement.dataset.theme === 'dark' ? 'light' : 'dark';

        applyTheme(nextTheme);

        try {
            localStorage.setItem(themePreferenceKey, nextTheme);
            localStorage.removeItem('northstar-theme');
        } catch (error) {
            console.error('The appearance preference could not be saved.', error);
            showToast('Theme changed, but this browser could not save your preference.');
        }
    });
});

const systemTheme = window.matchMedia('(prefers-color-scheme: dark)');

systemTheme.addEventListener('change', (event) => {
    try {
        if (localStorage.getItem(themePreferenceKey) === null) {
            applyTheme(event.matches ? 'dark' : 'light');
        }
    } catch (error) {
        console.error('The saved appearance preference could not be read.', error);
    }
});

if ('serviceWorker' in navigator) {
    window.addEventListener('load', () => {
        navigator.serviceWorker.register('/sw.js').catch((error) => {
            console.error('The application service worker could not be registered.', error);
        });
    });
}

let deferredInstallPrompt;

window.addEventListener('beforeinstallprompt', (event) => {
    event.preventDefault();
    deferredInstallPrompt = event;
});

document.querySelectorAll('[data-install-app]').forEach((button) => {
    button.addEventListener('click', async () => {
        if (!deferredInstallPrompt) {
            showToast('Use your browser menu to install OUT OF BOREDOM on this device.');

            return;
        }

        deferredInstallPrompt.prompt();
        await deferredInstallPrompt.userChoice;
        deferredInstallPrompt = null;
    });
});

const sidebar = document.querySelector('[data-sidebar-toggle]');
const sidebarPanel = document.querySelector('#erp-sidebar');
const sidebarScrim = document.querySelector('[data-sidebar-close]');

function closeSidebar() {
    sidebarPanel?.classList.remove('is-open');
    sidebarScrim?.classList.remove('is-visible');
    sidebar?.setAttribute('aria-expanded', 'false');
}

sidebar?.addEventListener('click', () => {
    const isOpen = sidebarPanel?.classList.toggle('is-open') ?? false;
    sidebarScrim?.classList.toggle('is-visible', isOpen);
    sidebar.setAttribute('aria-expanded', String(isOpen));
});

document.querySelector('[data-sidebar-dismiss]')?.addEventListener('click', closeSidebar);
sidebarScrim?.addEventListener('click', closeSidebar);
sidebarPanel?.querySelectorAll('a').forEach((link) => link.addEventListener('click', closeSidebar));

document.querySelector('[data-inventory-search]')?.addEventListener('input', (event) => {
    const query = event.target.value.trim().toLocaleLowerCase();
    const rows = [...document.querySelectorAll('[data-inventory-row]')];
    let visibleRows = 0;

    rows.forEach((row) => {
        const matches = row.textContent.toLocaleLowerCase().includes(query);
        row.hidden = !matches;
        visibleRows += Number(matches);
    });

    const emptyRow = document.querySelector('[data-empty-search]');

    if (emptyRow) {
        emptyRow.hidden = visibleRows > 0;
    }

});

document.querySelectorAll('[data-dynamic-form]').forEach((form) => {
    const type = form.dataset.dynamicForm;
    const template = document.querySelector(type === 'ingredients'
        ? '[data-ingredient-row-template]'
        : '[data-purchase-line-template]');
    const rows = form.querySelector('[data-dynamic-rows]');

    if (!template || !rows) {
        return;
    }

    form.querySelector('[data-add-row]')?.addEventListener('click', () => {
        const index = Number(form.dataset.nextIndex || 1);
        const row = template.content.cloneNode(true);

        row.querySelectorAll('[data-field]').forEach((field) => {
            field.name = type === 'ingredients'
                ? `ingredients[${index}][${field.dataset.field}]`
                : `lines[${index}][${field.dataset.field}]`;
        });
        form.dataset.nextIndex = String(index + 1);
        rows.append(row);
    });

    rows.addEventListener('click', (event) => {
        const removeButton = event.target.closest('[data-remove-row]');

        if (removeButton && rows.querySelectorAll('.cafe-dynamic-row').length > 1) {
            removeButton.closest('.cafe-dynamic-row').remove();
        }
    });
});

const posForm = document.querySelector('[data-pos-form]');

if (posForm) {
    const cart = new Map();
    const cartElement = posForm.querySelector('[data-pos-cart]');
    const inputContainer = posForm.querySelector('[data-pos-inputs]');
    const submitButton = posForm.querySelector('[data-pos-submit]');
    const currencyFormat = new Intl.NumberFormat(undefined, { minimumFractionDigits: 2, maximumFractionDigits: 2 });
    const taxRate = Number(posForm.dataset.taxRate || 0);
    const pricesIncludeTax = posForm.dataset.pricesIncludeTax === '1';

    function renderCart() {
        cartElement.replaceChildren();
        inputContainer.replaceChildren();
        let subtotalCents = 0;
        let itemIndex = 0;

        cart.forEach((item, id) => {
            subtotalCents += item.priceCents * item.quantity;

            const row = document.createElement('div');
            row.className = 'cafe-cart-row';
            const copy = document.createElement('span');
            copy.className = 'cafe-cart-item-copy';
            const title = document.createElement('strong');
            title.textContent = item.name;
            const price = document.createElement('small');
            price.textContent = `${currencyFormat.format(item.priceCents / 100)} each`;
            copy.append(title, price);

            const quantity = document.createElement('span');
            quantity.className = 'cafe-cart-quantity';
            const decrement = document.createElement('button');
            decrement.type = 'button';
            decrement.dataset.posDecrement = id;
            decrement.setAttribute('aria-label', `Remove one ${item.name}`);
            decrement.textContent = '−';
            const count = document.createElement('span');
            count.textContent = String(item.quantity);
            const increment = document.createElement('button');
            increment.type = 'button';
            increment.dataset.posIncrement = id;
            increment.setAttribute('aria-label', `Add one ${item.name}`);
            increment.textContent = '+';
            quantity.append(decrement, count, increment);

            const lineTotal = document.createElement('strong');
            lineTotal.className = 'cafe-cart-line-total';
            lineTotal.textContent = currencyFormat.format((item.priceCents * item.quantity) / 100);
            row.append(copy, quantity, lineTotal);
            cartElement.append(row);

            const idInput = document.createElement('input');
            idInput.type = 'hidden';
            idInput.name = `items[${itemIndex}][menu_item_id]`;
            idInput.value = String(id);
            const quantityInput = document.createElement('input');
            quantityInput.type = 'hidden';
            quantityInput.name = `items[${itemIndex}][quantity]`;
            quantityInput.value = String(item.quantity);
            inputContainer.append(idInput, quantityInput);
            itemIndex += 1;
        });

        if (cart.size === 0) {
            const empty = document.createElement('div');
            empty.className = 'cafe-empty-state';
            empty.textContent = 'Add items from the menu to begin.';
            cartElement.append(empty);
        }

        const taxCents = pricesIncludeTax
            ? Math.round((subtotalCents * taxRate) / (100 + taxRate))
            : Math.round((subtotalCents * taxRate) / 100);
        const totalCents = pricesIncludeTax ? subtotalCents : subtotalCents + taxCents;
        const displayedTax = posForm.querySelector('[data-pos-tax]');
        posForm.querySelector('[data-pos-subtotal]').textContent = currencyFormat.format(subtotalCents / 100);
        posForm.querySelector('[data-pos-total]').textContent = currencyFormat.format(totalCents / 100);

        if (displayedTax) {
            displayedTax.textContent = currencyFormat.format(taxCents / 100);
        }

        submitButton.disabled = cart.size === 0;
    }

    document.querySelectorAll('[data-pos-add]').forEach((button) => {
        button.addEventListener('click', () => {
            const id = button.dataset.id;
            const item = cart.get(id) || {
                name: button.dataset.name,
                priceCents: Math.round(Number(button.dataset.price) * 100),
                quantity: 0,
            };

            if (item.quantity < 99) {
                item.quantity += 1;
                cart.set(id, item);
                renderCart();
            }
        });
    });

    cartElement.addEventListener('click', (event) => {
        const increment = event.target.closest('[data-pos-increment]');
        const decrement = event.target.closest('[data-pos-decrement]');

        if (increment) {
            const item = cart.get(increment.dataset.posIncrement);
            if (item && item.quantity < 99) {
                item.quantity += 1;
            }
        } else if (decrement) {
            const id = decrement.dataset.posDecrement;
            const item = cart.get(id);
            if (item) {
                item.quantity -= 1;
                if (item.quantity <= 0) {
                    cart.delete(id);
                }
            }
        }

        renderCart();
    });

    posForm.querySelector('[data-pos-clear]')?.addEventListener('click', () => {
        cart.clear();
        renderCart();
    });
}

document.querySelectorAll('[data-notification], [data-more-activity], [data-period-toggle]').forEach((button) => {
    button.addEventListener('click', () => showToast('You’re all caught up. Live updates will appear here when connected.'));
});

function showToast(message) {
    const toast = document.querySelector('[data-toast]');

    if (!toast) {
        return;
    }

    toast.textContent = message;
    toast.classList.add('is-visible');
    window.clearTimeout(showToast.timeout);
    showToast.timeout = window.setTimeout(() => toast.classList.remove('is-visible'), 3000);
}
