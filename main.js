async function validateCounter (event) {
    let input = document.querySelector('#'+event.target.id);
    let measure = input.closest('.counter').querySelector('[data-measure]').dataset.measure;

    var keyCode = (event.which) ? event.which : event.keyCode;
    if (measure == 'pieces') {
        if (keyCode > 31 && ((keyCode < 48 || keyCode > 57) && keyCode > 31 && (keyCode < 96 || keyCode > 105))) {
            event.preventDefault();
        }
    } else {
        if (keyCode > 31 && ((keyCode < 48 || keyCode > 57) && keyCode > 31 && (keyCode < 96 || keyCode > 105))) {
            if(keyCode == 191 || keyCode == 190 || keyCode == 188) {
                return;
            }
            event.preventDefault();
        }
    }
}

function checkInputMaxValue (el) {
   if (Number(el.value) >= Number(el.max)) {
        if (Number.isInteger(Number(el.step))&&Number(el.step)!=0) {
            let temp = Math.floor(el.max/el.step);
            el.value = temp*el.step;
            return;
        }
        el.value = el.max;
    }
}