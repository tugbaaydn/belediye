document.addEventListener('DOMContentLoaded', () => {
    const toggle = document.getElementById(
        'sidebarToggle'
    );

    const sidebar = document.getElementById(
        'sidebar'
    );

    if (toggle && sidebar) {
        toggle.addEventListener('click', () => {
            sidebar.classList.toggle('open');
        });
    }

    document
        .querySelectorAll('[data-toggle-password]')
        .forEach(button => {
            button.addEventListener('click', () => {
                const input = document.querySelector(
                    button.dataset.togglePassword
                );

                if (!input) {
                    return;
                }

                input.type =
                    input.type === 'password'
                        ? 'text'
                        : 'password';

                const icon = button.querySelector('i');

                if (icon) {
                    icon.classList.toggle('fa-eye');
                    icon.classList.toggle('fa-eye-slash');
                }
            });
        });

    const departmentSelect =
        document.getElementById('departmentSelect');

    const staffSelect =
        document.getElementById('staffSelect');

    if (departmentSelect && staffSelect) {
        const filterStaff = () => {
            const selectedDepartment =
                departmentSelect.value;

            [...staffSelect.options].forEach(
                (option, index) => {
                    if (index === 0) {
                        return;
                    }

                    const visible =
                        !selectedDepartment ||
                        option.dataset.department ===
                            selectedDepartment;

                    option.hidden = !visible;

                    if (!visible && option.selected) {
                        staffSelect.value = '0';
                    }
                }
            );
        };

        departmentSelect.addEventListener(
            'change',
            filterStaff
        );

        filterStaff();
    }

    const locationButton =
        document.getElementById('getLocationButton');

    const latitudeInput =
        document.getElementById('latitude');

    const longitudeInput =
        document.getElementById('longitude');

    const locationStatus =
        document.getElementById('locationStatus');

    if (
        latitudeInput &&
        longitudeInput &&
        locationStatus &&
        latitudeInput.value &&
        longitudeInput.value
    ) {
        locationStatus.textContent =
            `Konum eklendi: ` +
            `${Number(latitudeInput.value).toFixed(6)}, ` +
            `${Number(longitudeInput.value).toFixed(6)}`;

        locationStatus.classList.add(
            'location-success'
        );
    }

    if (
        locationButton &&
        latitudeInput &&
        longitudeInput &&
        locationStatus
    ) {
        locationButton.addEventListener(
            'click',
            () => {
                if (!navigator.geolocation) {
                    locationStatus.textContent =
                        'Tarayıcınız konum özelliğini desteklemiyor.';

                    locationStatus.classList.add(
                        'location-error'
                    );

                    return;
                }

                locationButton.disabled = true;

                locationStatus.textContent =
                    'Konum bilgisi alınıyor...';

                locationStatus.classList.remove(
                    'location-error',
                    'location-success'
                );

                navigator.geolocation.getCurrentPosition(
                    position => {
                        latitudeInput.value =
                            position.coords.latitude.toFixed(7);

                        longitudeInput.value =
                            position.coords.longitude.toFixed(7);

                        locationStatus.textContent =
                            'Konum başarıyla eklendi. ' +
                            'Yaklaşık doğruluk: ' +
                            Math.round(
                                position.coords.accuracy
                            ) +
                            ' metre.';

                        locationStatus.classList.add(
                            'location-success'
                        );

                        locationButton.innerHTML =
                            '<i class="fa-solid ' +
                            'fa-circle-check me-2"></i>' +
                            'Konum Eklendi';

                        locationButton.disabled = false;
                    },

                    error => {
                        const messages = {
                            1:
                                'Konum izni verilmedi. ' +
                                'Tarayıcı ayarlarından ' +
                                'konum iznini açınız.',

                            2:
                                'Konum bilgisi alınamadı. ' +
                                'Konum servisini kontrol ediniz.',

                            3:
                                'Konum alma işlemi ' +
                                'zaman aşımına uğradı.'
                        };

                        locationStatus.textContent =
                            messages[error.code] ||
                            'Konum alınırken hata oluştu.';

                        locationStatus.classList.add(
                            'location-error'
                        );

                        locationButton.disabled = false;
                    },

                    {
                        enableHighAccuracy: true,
                        timeout: 15000,
                        maximumAge: 0
                    }
                );
            }
        );
    }

    const photoInput =
        document.getElementById('attachment');

    const previewWrap =
        document.getElementById(
            'photoPreviewWrap'
        );

    const previewImage =
        document.getElementById(
            'photoPreview'
        );

    if (
        photoInput &&
        previewWrap &&
        previewImage
    ) {
        photoInput.addEventListener(
            'change',
            () => {
                const file =
                    photoInput.files?.[0];

                if (!file) {
                    previewWrap.classList.add(
                        'd-none'
                    );

                    previewImage.removeAttribute(
                        'src'
                    );

                    return;
                }

                if (!file.type.startsWith('image/')) {
                    return;
                }

                previewImage.src =
                    URL.createObjectURL(file);

                previewWrap.classList.remove(
                    'd-none'
                );
            }
        );
    }

    const complaintForm =
        document.getElementById(
            'complaintForm'
        );

    if (
        complaintForm &&
        latitudeInput &&
        longitudeInput
    ) {
        complaintForm.addEventListener(
            'submit',
            event => {
                if (
                    !latitudeInput.value ||
                    !longitudeInput.value
                ) {
                    event.preventDefault();

                    locationStatus.textContent =
                        'Başvuru göndermeden önce ' +
                        'Konumumu Al butonuna basmalısınız.';

                    locationStatus.classList.add(
                        'location-error'
                    );

                    locationButton?.scrollIntoView({
                        behavior: 'smooth',
                        block: 'center'
                    });
                }
            }
        );
    }

    setTimeout(() => {
        document
            .querySelectorAll(
                '.toast-container-custom'
            )
            .forEach(element => element.remove());
    }, 4500);
});