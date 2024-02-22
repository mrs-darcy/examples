BX.namespace('BX.Sale.PersonalOrderComponent');
(function () {
    BX.Sale.PersonalOrderComponent.NDAOrderDetail = {

        init: function (params) {
            this.orderId = params.id;
            orderId = this.orderId;

            BX.bind(BX('orderCommentSave'), 'click', () => {
                BX.ajax.runAction('NDA:main.api.orderaction.setcomment', {
                    data: {
                        orderId: this.orderId,
                        comment: BX('orderCommentText').value
                    }
                }).then(function (response) {
                    console.log(response);
                }, function (response) {
                    console.error(response);
                });
            }, this);

            var getInvoice = document.querySelectorAll('a[data-entity="invoice"]');
            getInvoice.forEach((element) => {
                BX.bind(element, 'click', function(e) {
                    BX(element).setAttribute('disabled', '');
                    BX.ajax.runAction('NDA:main.api.orderaction.getinvoice', {
                        data: {
                            orderId: orderId
                        }
                    }).then(function (response) {
                        BX.adjust(BX.findChild(BX('invoice-modal'), {'tag': 'div', 'class': 'notification'}, true), {text: 'Запрос успешно отправлен. Счет на оплату будет готов в ближайшее время.'});
                        if (response['data']['success'] != true) {
                            BX.adjust(BX.findChild(BX('invoice-modal'), {'tag': 'div', 'class': 'notification'}, true), {text: 'Произошла ошибка при отправке запроса на получение счета.'});
                            BX(element).removeAttribute('disabled');
                        }
                        UIkit.modal('#invoice-modal').show(); 
                    }, function (response) {
                        BX.adjust(BX.findChild(BX('invoice-modal'), {'tag': 'div', 'class': 'notification'}, true), {text: 'Произошла ошибка при отправке запроса на получение счета.'});
                        UIkit.modal('#invoice-modal').show();
                        BX(element).removeAttribute('disabled');
                    });
                        
                    BX.PreventDefault(e);
                }, this);
            });

            var upload = document.querySelectorAll('input[data-entity="upload"]');
            var uploadForm = document.querySelectorAll('form[data-entity="upload"]');

            upload.forEach(function(uploadEl, key){
                BX.bind(uploadEl, 'bxchange', () => {
                    let type = uploadEl.getAttribute('data-type');
                    let files = uploadEl.files;
                    let orderId = this.orderId;
                    let data = new FormData(uploadForm[key]);
                    let uploadButton = BX.findChild(BX(uploadForm[key]), {'tag': 'label'});

                    $.each(files, function(key, value){
                        data.append(key, value);
                    });
                    data.append('orderId', orderId);
                    data.append('type', type);

                    BX(uploadButton).setAttribute('disabled', '');
                    var wait = BX.showWait(uploadForm[key]);
                    BX.ajax.runAction('NDA:main.api.orderaction.uploadorderdocuments', {
                        mode: 'ajax',
                        data: data,
                        method: 'POST',
                    }).then(function (response) {
                        BX.closeWait(uploadForm[key], wait);
                        BX.adjust(BX.findChild(BX('upload-modal'), {'tag': 'div', 'class': 'notification'}, true), {text: 'Файл загружен успешно.'});
                        if (response['data']['success'] != true) {
                            BX.adjust(BX.findChild(BX('upload-modal'), {'tag': 'div', 'class': 'notification'}, true), {text: 'Не удалось загрузить файл, попробуйте еще раз.'});
                            BX(uploadButton).removeAttribute('disabled');
                        }
                        UIkit.modal('#upload-modal').show();
                    }, function (response) {
                        BX.closeWait(uploadForm[key], wait);
                        BX.adjust(BX.findChild(BX('upload-modal'), {'tag': 'div', 'class': 'notification'}, true), {text: 'Не удалось загрузить файл, попробуйте еще раз.'});
                        UIkit.modal('#upload-modal').show();
                        BX(uploadButton).removeAttribute('disabled');
                    });
                }, this);
            });

            BX.ready(function(){
                BX.bindDelegate(
                   document.body, 'click', { tagName: 'button', className: 'btn', attribute: { 'data-entity': 'print'} }, BX.proxy(printOrderDocument, this))
            });

            this.initSortButtons();

            setOrderDocument = function(orderDocumentFrame) {
                let content = orderDocumentFrame.contentWindow;
                content.onload = function () {
                    content.focus();
                    content.print();       
                }
            }

            printOrderDocument = function() {
                let buttonUrl = BX.findPreviousSibling(BX.proxy_context, {className : 'btn'}).href;
                let orderDocumentFrame = BX.create({ 
                    tag: 'iframe', 
                    attrs: { 'src': buttonUrl,  'id': 'documentPrint', 'name': 'documentPrint' }, 
                    style: { display: 'none', position: 'fixed', right: '0', bottom: '0'}, 
                });

                BX.append(orderDocumentFrame, document.body);
                orderDocumentFrame.onload = setOrderDocument(orderDocumentFrame);
            }

            var copyOrderBtn = document.querySelector('a[data-entity="copy"]');
            BX.bind(BX(copyOrderBtn), 'click', () => {
                copyOrderBtn.setAttribute('disabled', '');
            }, this);
        },

        initSortButtons: function(){
            const buttons = document.querySelectorAll('.table__sort');

            if (buttons) {
                for (var i = 0; i < buttons.length; i++) {
                    buttons[i].addEventListener('click', async function(event) {
                        let button = event.target;
                        if(event.target.tagName != 'button'){
                            button = event.target.closest('button');
                        }
                        let sort = button.dataset.sort;
                        let order = false;
                        if (button.classList.contains('active-up')) {
                            order = 'desc';
                        } else if (button.classList.contains('active-down')) {
                            let url = new URL(window.location.href);
                            url.searchParams.delete('sort');
                            url.searchParams.delete('order');
                            window.location.href = url.href;
                            return;
                        } else {
                            order = 'asc';
                        }
                        let url = new URL(window.location.href);
                        url.searchParams.set('sort', sort);
                        url.searchParams.set('order', order);
                        window.location.href = url.href;
                    });
                }
            }

        }
    };
})();