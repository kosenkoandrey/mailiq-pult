<div id="costs-period" class="btn-group m-b-15 m-r-15">
    <button data-period="yesterday" type="button" class="btn btn-default waves-effect">Вчера</button>
    <button data-period="1 weeks" type="button" class="btn btn-default waves-effect">Неделя</button>
    <button data-period="1 months" type="button" class="btn btn-default waves-effect">Месяц</button>
    <button data-period="3 months" type="button" class="btn btn-default waves-effect">Квартал</button>
    <button data-period="1 years" type="button" class="btn btn-default waves-effect">Год</button>
</div>
<div class="btn-group m-b-15">
    <button id="costs-calendar" type="button" class="btn btn-default waves-effect"><i class="zmdi zmdi-calendar"></i> <span id="costs-calendar-from">...</span> - <span id="costs-calendar-to">...</span></button>
</div>
<div id="costs-chart" class="m-b-20">
    <div class="text-center">
        <div class="preloader pl-xxl">
            <svg class="pl-circular" viewBox="25 25 50 50">
                <circle class="plc-path" cx="50" cy="50" r="20" />
            </svg>
        </div>
    </div>
</div>
<div id="costs-chart-legend"></div>
<input id="costs-date-from" type="hidden">
<input id="costs-date-to" type="hidden">
<div id="costs-list"></div>