const countrySelect = document.getElementById('country');
const cityList = document.getElementById('cityList');
const message = document.getElementById('message');

countrySelect.addEventListener('change', function () {
    const selectedCountry = this.value;

    cityList.innerHTML = '';
    message.textContent = '';

    if (!selectedCountry) {
        message.textContent = 'Сначала выберите страну.';
        return;
    }

    fetch('cities.json')
        .then(response => {
            if (!response.ok) {
                throw new Error('Ошибка загрузки JSON');
            }
            return response.json();
        })
        .then(data => {
            if (data[selectedCountry]) {
                data[selectedCountry].forEach(city => {
                    const li = document.createElement('li');
                    li.textContent = city;
                    cityList.appendChild(li);
                });
            } else {
                message.textContent = 'Для выбранной страны города не найдены.';
            }
        })
        .catch(error => {
            message.textContent = 'Ошибка при загрузке данных.';
            console.error(error);
        });
});