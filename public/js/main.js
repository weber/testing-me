'use strict';

//хак css
function firefoxFix () {

    if (/firefox/.test(window.navigator.userAgent.toLowerCase())) {

        let tds = document.getElementsByTagName('td');

        for (let index = 0; index < tds.length; index++) {
            tds[index].innerHTML = `<div class="ff-fix">${tds[index].innerHTML}</div>`;
        };

        let style = `
            <style>
                td { padding: 0 !important; }
                td:hover::before, td:hover::after { background-color: transparent !important; }
            </style>
            `;
        document.head.insertAdjacentHTML('beforeEnd', style);
    }
}

function tableViewModel () {
    let self = this;
    this.orderDate = ko.observable('');
    this.orderTitle = ko.observable('');
    this.selectCol = ko.observable('');
    this.selectCol1 = ko.observable(0);
    // this.isSelectRow = ko.observable(false);
    this.selectRow = ko.observable('');
    this.dataNews = ko.observable('');
    this.order = ko.observable('');
    this.isLock = ko.observable('');
    this.isLoaded = ko.observable(false);
    this.hasShowModal = ko.observable(false);
    this.modalDatetimeStr = ko.observable('');
    this.modalDatetime = ko.observable('');
    this.modalImage = ko.observable('');
    this.modalTitle = ko.observable('');
    this.modalText = ko.observable('');


    /**
     * Сортирует массив данных, меняет в хедаре таблице маркеры направления, выделяет  сортируемый столбец
     * @param {stirng} sortBy тип поля(Date, Title)
     */
    this.orderBy = function (sortBy) {
        let sortItem1 = '';
        let sortItem2 = '';

        if (sortBy === 'Date') {
            sortItem1 = `order${sortBy}`;
            sortItem2 = 'orderTitle';
            this.selectCol('s-select1');
        } else {
            sortItem1 = `order${sortBy}`;
            sortItem2 = 'orderDate';
            this.selectCol('s-select3');
        }

        if (R.isEmpty(this[sortItem1]()) || this[sortItem1]() === 'b-link_btn--desc') {
            this[sortItem1]('b-link_btn--asc');
            this[sortItem2]('');
            this.order('asc');
            this.dataNews(sortDataBy(this.order(), this.dataNews(), sortBy));
        } else if (this[sortItem1]() === 'b-link_btn--asc') {
            this[sortItem1]('b-link_btn--desc');
            this[sortItem2]('');
            this.order('desc');
            // сортировка данных по типу поля 
            this.dataNews(sortDataBy(this.order(), this.dataNews(), sortBy));
        }
    };

    /**
     * Сортирует данные по указанным параметрам
     * @param {string} orderBy - напрвление сортировки (asc/desc)
     * @param {Array<Object>} data - сортируемые данные
     * @param {string} type  - тип(столбец сортируемый)
     * @example ```
     * let data = [
     *  {id:1, datetime: '2018-02-17T09:24:00Z'},
     * ...
     * ];
     * sortDataBy('asc', data, 'Date');
     * ```
     */
    function sortDataBy (orderBy, data, type) {
        let _orderBy = (R.isEmpty(orderBy) || orderBy === 'asc') ? true : false;
        let sortFn = () => {};

        // сортировка по дате
        if (type === 'Date') {
            const dateSortFn = (a, b) => new Date(a.datetime) - new Date(b.datetime);
            sortFn = dateSortFn;
        }

        // сортировка по алфавиту
        if (type === 'Title') {
            const titleSortFn = (a, b) => {
                if (a.title > b.title) return 1;
                if (a.title < b.title) return -1;
                return 0;
            };
            sortFn = titleSortFn;
        }

        let items = R.sort(sortFn, data);

        if (!_orderBy) {
            return R.reverse(items);
        } else {
            return items;
        }
    }

    /**
    * Возвращяет закеширование данные
    */
    function getDataAll () {
        fetch('data.json')
            .then(res => res.json())
            .then(json => {
                let data = json.map(item => {
                    item['isSelectRow'] = ko.observable(false);
                    return item;
                });
                self.dataNews(data);
            });
    }

    /**
    * Обновление данных новостей
    */
    function updateData () {
        self.isLock('s-lock');
        self.isLoaded(true);
        fetch('/news/reload')
            .then(res => res.json())
            .then(json => {
                let data = json.map(item => {
                    item['isSelectRow'] = ko.observable(false);
                    return item;
                });
                self.dataNews(data);
                self.isLock('');
                self.isLoaded(false);
            });
    }

    /**
     * Выделяет текущую строку таблицы.
     * @param {*} data - данные текущей строки
     */
    this.selectRowHandler = function (data) {
        let resetIsSelectRow = self.dataNews().map(item => {
            item.isSelectRow(false);
            return item;
        });
        self.dataNews(resetIsSelectRow);
        data.isSelectRow(!data.isSelectRow());
    };

    /**
     * Форматирует дату 
     * @param {DateTime} date 
     * @param {bool} monthString - флаг форматирование, true - вернет месяц строковой, 
     * false - вернет месяц числовой с ведущим нулем.
     */
    this.formatDate = function (date, monthString = false) {
        const MONTH_NAMES = {
            '0': 'Январь',
            '1': 'Февраль',
            '2': 'Март',
            '3': 'Апрель',
            '4': 'Май',
            '5': 'Июнь',
            '6': 'Июль',
            '7': 'Август',
            '8': 'Сентябрь',
            '9': 'Октябрь',
            '10': 'Ноябрь',
            '11': 'Декабрь',
        };

        let myDate = new Date(date);
        let month = myDate.getMonth();
        let year = myDate.getYear() + 1900;
        let day = myDate.getDate();
        let minute = myDate.getMinutes();
        let hours = myDate.getHours();

        day = (day < 10) ? `0${day}` : day;

        if (monthString === false) {
            month++;
            month = (month < 10) ? `0${month}` : month;
            return `${day}.${month}.${year} ${hours}:${minute}`;
        } else {
            return `${day} ${MONTH_NAMES[month]} ${year} ${hours}:${minute}`;
        }
    };

    this.updateData = function () {
        updateData();
    };

    /**
     * Отобразить новость в модальном окне
     */
    this.showNews = function (data) {

        let datetime = self.formatDate(data.datetime, true);
        self.modalDatetimeStr(datetime);
        self.modalDatetime(datetime);
        self.modalImage(data.image);
        self.modalTitle(data.title);
        self.modalText(data.text);

        self.hasShowModal(true);
        self.isLoaded(false);
        self.isLock('s-lock');
    };
    /**
     * Скрыть модальное окно
     */
    this.hideNews = function () {
        self.isLock('');
        self.hasShowModal(false);
    };

    // конструктор данных новостей
    (function () {
        getDataAll();
    })();
}
// добавляет дополнительный обработчик класса
ko.bindingHandlers['css2'] = ko.bindingHandlers.css;

// обрабочик одиночного и двойного клика
ko.bindingHandlers.clickHandler= {
    init: function(element, valueAccessor, allBindingsAccessor, viewModel, bindingContext) {
        let singleHandler = valueAccessor().click;
        let doubleHandler = valueAccessor().dblclick;
        let delay = valueAccessor().delay || 200;
        let clicks = 0;
    
        element.addEventListener('click', function (e) {
            clicks++;
            if (clicks === 1) {
                setTimeout(() => {
                    if (clicks === 1) {
                        if (singleHandler !== undefined) {
                            singleHandler.call(viewModel, bindingContext.$data, e);
                        }
                    } else {
                        if (doubleHandler !== undefined) {
                            doubleHandler.call(viewModel, bindingContext.$data, e);
                        }
                    }
                    clicks = 0;
                }, delay);
            }
        }, false);
    }
};
ko.applyBindings(new tableViewModel());
