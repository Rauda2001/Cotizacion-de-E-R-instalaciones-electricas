(() => {
    const money = new Intl.NumberFormat('es-MX', { style: 'currency', currency: 'MXN' });
    const quantityDialog = document.getElementById('quantity-dialog');
    const quantityProduct = document.getElementById('quantity-product');
    const quantityDetails = document.getElementById('quantity-details');
    const quantityInput = document.getElementById('product-quantity');
    let pendingProduct = null;
    let returnFocus = null;

    // Mantiene la fecha de vigencia 30 días después de la fecha de cotización.
    function updateValidityDate() {
        const quoteDate = document.getElementById('fecha');
        const validityDate = document.getElementById('vigencia_hasta');
        if (!quoteDate.value) {
            validityDate.value = '';
            return;
        }

        const date = new Date(`${quoteDate.value}T00:00:00`);
        date.setDate(date.getDate() + 30);
        validityDate.value = [
            date.getFullYear(),
            String(date.getMonth() + 1).padStart(2, '0'),
            String(date.getDate()).padStart(2, '0'),
        ].join('-');
    }

    // Normaliza acentos y mayúsculas para que la búsqueda sea más flexible.
    function normalizeText(value) {
        return value.normalize('NFD').replace(/[\u0300-\u036f]/g, '').toLocaleLowerCase();
    }

    // Oculta sugerencias y restablece los atributos de accesibilidad del buscador.
    function closeSuggestions(searchInput) {
        const list = searchInput.parentElement.querySelector('.item-suggestions');
        list.hidden = true;
        searchInput.setAttribute('aria-expanded', 'false');
        searchInput.removeAttribute('aria-activedescendant');
        list.querySelectorAll('.item-suggestion').forEach((option) => option.classList.remove('active'));
    }

    // Añade una partida al formulario con la cantidad confirmada por el usuario.
    function addProduct(section, searchInput, option, quantity) {
        const template = section.querySelector('.line-template');
        const line = template.content.firstElementChild.cloneNode(true);
        const select = line.querySelector('.line-item');
        select.value = option.value;
        line.querySelector('.line-item-name').textContent = option.textContent.trim();
        line.querySelector('.line-qty').value = quantity;
        section.querySelector('.line-list').append(line);
        searchInput.value = '';
        closeSuggestions(searchInput);
        refreshTotals();
    }

    // Abre el cuadro de cantidad; si el artículo ya existe, permite actualizarlo.
    function requestProductQuantity(section, searchInput, option) {
        closeSuggestions(searchInput);
        const existingLine = Array.from(section.querySelectorAll('.quote-line'))
            .find((line) => line.querySelector('.line-item').value === option.value);
        pendingProduct = { section, searchInput, option, existingLine };
        returnFocus = searchInput;
        quantityProduct.textContent = option.textContent.trim();
        const selectedOption = existingLine
            ? existingLine.querySelector('.line-item').selectedOptions[0]
            : option;
        const unitPrice = Number(selectedOption?.dataset.price) || 0;
        const unit = selectedOption?.dataset.unit || 'unidad';
        quantityDetails.textContent = existingLine
            ? `Cantidad actual: ${existingLine.querySelector('.line-qty').value} · ${money.format(unitPrice)} / ${unit}`
            : `${money.format(unitPrice)} / ${unit}`;
        quantityInput.value = existingLine ? existingLine.querySelector('.line-qty').value : '1';
        document.getElementById('quantity-dialog-title').textContent = existingLine
            ? 'Actualizar cantidad'
            : 'Selecciona la cantidad';
        document.getElementById('confirm-product-quantity').textContent = existingLine
            ? 'Actualizar artículo'
            : 'Agregar artículo';
        quantityDialog.showModal();
        quantityInput.focus();
        quantityInput.select();
    }

    // Guarda la cantidad nueva o actualizada y vuelve el foco al buscador.
    function confirmProduct() {
        if (!pendingProduct || !quantityInput.reportValidity()) {
            return;
        }

        const { section, searchInput, option, existingLine } = pendingProduct;
        if (existingLine) {
            existingLine.querySelector('.line-qty').value = quantityInput.value;
            refreshTotals();
        } else {
            addProduct(section, searchInput, option, quantityInput.value);
        }
        searchInput.value = '';
        closeSuggestions(searchInput);
        pendingProduct = null;
        quantityDialog.close();
    }

    // Busca coincidencias de catálogo y marca la primera opción disponible.
    function showSuggestions(searchInput) {
        const section = searchInput.closest('.quote-lines');
        const select = section.querySelector('.line-template').content.querySelector('.line-item');
        const list = section.querySelector('.item-suggestions');
        const query = normalizeText(searchInput.value.trim());
        if (!query) {
            closeSuggestions(searchInput);
            return;
        }

        const matches = Array.from(select.options)
            .filter((option) => option.value !== '' && normalizeText(option.textContent).includes(query))
            .slice(0, 8);

        searchInput.removeAttribute('aria-activedescendant');
        list.replaceChildren();
        if (matches.length === 0) {
            const empty = document.createElement('div');
            empty.className = 'item-suggestions-empty';
            empty.textContent = 'No se encontraron artículos.';
            list.append(empty);
        } else {
            matches.forEach((option, index) => {
                const result = document.createElement('button');
                result.type = 'button';
                result.className = 'item-suggestion';
                result.id = `${searchInput.id}-option-${index}`;
                result.dataset.value = option.value;
                result.setAttribute('role', 'option');
                result.setAttribute('aria-selected', option.value === select.value ? 'true' : 'false');
                const name = document.createElement('span');
                name.className = 'item-suggestion-name';
                name.textContent = option.textContent.trim();
                const details = document.createElement('span');
                details.className = 'item-suggestion-details';
                details.textContent = `${option.dataset.unit || ''} · ${money.format(Number(option.dataset.price) || 0)}`;
                result.append(name, details);
                result.addEventListener('mousedown', (event) => event.preventDefault());
                result.addEventListener('click', () => {
                    requestProductQuantity(section, searchInput, option);
                });
                list.append(result);
            });
        }

        list.hidden = false;
        searchInput.setAttribute('aria-expanded', 'true');
        if (matches.length > 0) {
            setActiveSuggestion(searchInput, Array.from(list.querySelectorAll('.item-suggestion')), 0);
        }
    }

    // Recalcula los subtotales y el total preliminar mostrado en el formulario.
    function refreshTotals() {
        const totals = { material: 0, service: 0 };
        // Conecta buscadores, partidas y atajos de teclado en cada sección.
        document.querySelectorAll('.quote-lines').forEach((section) => {
            const kind = section.dataset.lineKind;
            section.querySelectorAll('.quote-line').forEach((line) => {
                const select = line.querySelector('.line-item');
                const option = select.options[select.selectedIndex];
                const quantity = Number(line.querySelector('.line-qty').value) || 0;
                const price = Number(option?.dataset.price) || 0;
                totals[kind] += quantity * price;
                line.querySelector('.line-price-value').textContent = option?.value
                    ? `${money.format(price)} / ${option.dataset.unit || ''}`
                    : 'Se calcula al guardar';
            });
        });

        document.getElementById('materials-total').textContent = money.format(totals.material);
        document.getElementById('services-total').textContent = money.format(totals.service);
        document.getElementById('grand-total').textContent = money.format(totals.material + totals.service);
    }

    document.querySelectorAll('.quote-lines').forEach((section) => {
        const lineList = section.querySelector('.line-list');
        const searchInput = section.querySelector('.item-search');
        const suggestions = section.querySelector('.item-suggestions');
        suggestions.id = `${searchInput.id}-list`;
        searchInput.setAttribute('aria-controls', suggestions.id);
        lineList.querySelectorAll('.quote-line').forEach((line) => {
            const select = line.querySelector('.line-item');
            line.querySelector('.line-item-name').textContent = select.options[select.selectedIndex].textContent.trim();
        });

        // Permite quitar partidas y actualizar los importes al cambiar cantidades.
        lineList.addEventListener('click', (event) => {
            if (event.target.closest('.remove-line')) {
                event.target.closest('.quote-line').remove();
                refreshTotals();
            }
        });
        lineList.addEventListener('input', (event) => {
            refreshTotals();
        });
        lineList.addEventListener('focusin', (event) => {
            if (event.target.matches('.line-qty')) {
                event.target.select();
            }
        });
        section.addEventListener('input', (event) => {
            if (event.target.matches('.item-search')) {
                showSuggestions(searchInput);
            }
        });
        searchInput.addEventListener('focusout', () => closeSuggestions(searchInput));
        // Soporta navegación de sugerencias y captura de cantidades con teclado.
        section.addEventListener('keydown', (event) => {
            if (event.target.matches('.line-qty') && event.key === 'Tab' && !event.shiftKey) {
                event.preventDefault();
                searchInput.focus();
                return;
            }
            if (event.target.matches('.line-qty') && event.key === 'Enter') {
                event.preventDefault();
                event.target.form.requestSubmit();
                return;
            }
            if (!event.target.matches('.item-search')) {
                return;
            }

            const list = suggestions;
            let options = Array.from(list.querySelectorAll('.item-suggestion'));
            let activeIndex = options.findIndex((option) => option.id === searchInput.getAttribute('aria-activedescendant'));

            if (event.key === 'ArrowDown') {
                event.preventDefault();
                if (list.hidden) {
                    showSuggestions(searchInput);
                    options = Array.from(list.querySelectorAll('.item-suggestion'));
                }
                activeIndex = options.findIndex((option) => option.id === searchInput.getAttribute('aria-activedescendant'));
                if (options.length > 0) {
                    setActiveSuggestion(searchInput, options, activeIndex < 0 ? 0 : Math.min(activeIndex + 1, options.length - 1));
                }
            } else if (event.key === 'ArrowUp') {
                event.preventDefault();
                if (list.hidden) {
                    showSuggestions(searchInput);
                    options = Array.from(list.querySelectorAll('.item-suggestion'));
                }
                activeIndex = options.findIndex((option) => option.id === searchInput.getAttribute('aria-activedescendant'));
                if (options.length > 0) {
                    setActiveSuggestion(searchInput, options, activeIndex < 0 ? options.length - 1 : Math.max(activeIndex - 1, 0));
                }
            } else if (event.key === 'Enter' && activeIndex >= 0) {
                event.preventDefault();
                options[activeIndex].click();
            } else if (event.key === 'Enter') {
                event.preventDefault();
                searchInput.form.requestSubmit();
            } else if (event.key === 'Escape') {
                closeSuggestions(searchInput);
            }
        });
    });

    document.getElementById('confirm-product-quantity').addEventListener('click', confirmProduct);
    document.getElementById('cancel-product-quantity').addEventListener('click', () => quantityDialog.close());
    quantityInput.addEventListener('keydown', (event) => {
        if (event.key === 'Enter') {
            event.preventDefault();
            confirmProduct();
        }
    });
    quantityDialog.addEventListener('close', () => {
        pendingProduct = null;
        const searchInput = returnFocus;
        returnFocus = null;
        if (searchInput?.isConnected) {
            window.setTimeout(() => {
                searchInput.focus({ preventScroll: true });
                searchInput.setSelectionRange(searchInput.value.length, searchInput.value.length);
            }, 50);
        }
    });

    // Sincroniza el resaltado visual y ARIA de la sugerencia activa.
    function setActiveSuggestion(searchInput, options, activeIndex) {
        options.forEach((option, index) => {
            const isActive = index === activeIndex;
            option.classList.toggle('active', isActive);
            option.setAttribute('aria-selected', isActive ? 'true' : 'false');
        });
        const activeOption = options[activeIndex];
        searchInput.setAttribute('aria-activedescendant', activeOption.id);
        activeOption.scrollIntoView({ block: 'nearest' });
    }

    // Inicializa vigencia y totales con los datos actuales del formulario.
    document.getElementById('fecha').addEventListener('change', updateValidityDate);
    updateValidityDate();
    refreshTotals();
})();
