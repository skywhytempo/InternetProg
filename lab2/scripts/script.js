function validateFormPlain() {
    ['textError', 'cbError', 'rbError'].forEach(id => {
        const el = document.getElementById(id);
        if (el) { el.textContent = ''; el.style.display = 'none'; }
    });

    let valid = true;

    const textValue = document.getElementById('textField');
    if (textValue && /[%$#@^]/.test(textValue.value)) {
        showError('textError', 'Запрещённые символы: %, $, #, @, ^');
        valid = false;
    }  

    const check1 = document.getElementById('cb1');
    const check3 = document.getElementById('cb3');
    const check4 = document.getElementById('cb4');
    if (!check1?.checked || !check3?.checked || !check4?.checkes) {
        showError('cbError', 'Необходимо отметить Опцию 1 3 и 4');
        valid = false;
    }

    const radios = document.querySelectorAll('input[name="rb"]');
    if (!Array.from(radios).some(r => r.checked)) {
        showError('rbError', 'Должна быть выбрана хотя бы одна радиокнопка');
        valid = false;
    } 

    return valid;
}

function showError(id, msg) {
    const el = document.getElementById(id);
    if (el) { el.textContent = msg; el.style.display = 'block'; }
} 

// LocalStorage

function saveFormData() {
    const formData = {
        textField: document.getElementById('textField').value,
        cb1: document.getElementById('cb1').checked,
        cb2: document.getElementById('cb2').checked,
        cb3: document.getElementById('cb3').checked,
        rb: document.querySelector('input[name="rb"]:checked')?.value || ''
    };
    localStorage.setItem('myForm', JSON.stringify(formData));
}

function loadFormData() {
    const savedData = localStorage.getItem('myForm');
    if (!savedData) return;
    const data = JSON.parse(savedData);

    $('#textField').val(data.textField || '');
    $('#cb1').prop('checked', !!data.cb1);
    $('#cb2').prop('checked', !!data.cb2);
    $('#cb3').prop('checked', !!data.cb3);
    if (data.rb) {
        $('input[name="rb"][value="' + data.rb + '"]').prop('checked', true);
    }
} 
// ========== Этап 2: jQuery Validation ==========

$(document).ready(function () {

    loadFormData();

    jQuery.validator.addMethod('noBadChars', function (value, element) {
        return this.optional(element) || !/[%$#@^]/.test(value);
    }, 'Запрещённые символы: %, $, #, @, ^');

    jQuery.validator.addMethod('cbBothRequired', function () {
        return $('#cb1').is(':checked') && $('#cb3').is(':checked') && !$('#cb4').is(':checked');
    }, 'Необходимо отметить Опцию 1, 3 И 4');

    var validator = $('#myForm').validate({
        rules: {
            textField:  { noBadChars: true },
            cb1:        { cbBothRequired: true },
            rb:         { required: true }
        },
        messages: {
            textField:  { noBadChars: 'Запрещённые символы: %, $, #, @, ^' },
            cb1:        { cbBothRequired: 'Необходимо отметить Опцию 1, 3 И 4' },
            rb:         { required: 'Должна быть выбрана хотя бы одна радиокнопка' }
        },
        errorElement: 'span',
        errorClass: 'field-error',

        errorPlacement: function (error, element) {
            var name = element.attr('name');
            if (name === 'cb1') {
                error.appendTo('#cbError');
            } else if (name === 'rb') {
                error.appendTo('#rbError');
            } else {
                error.appendTo('#textError');
            }
        },

        highlight:   function (element) { $(element).addClass('error'); },
        unhighlight: function (element) { $(element).removeClass('error'); },

        submitHandler: function () {
            saveFormData(); 
            alert('Форма успешно заполнена! Данные сохранены.');
        }
    });

    $('#cb3').on('change', function () {
        validator.element('#cb1');
    });
});




