jQuery(document).ready(function($){
    $(document).on('click', '.open-quick-edit', function(e) {
        e.preventDefault();
        $(this).parents('td').find('.sidenav').css("width", 550);
        document.getElementById("main").style.marginLeft = "0px";
        document.getElementById("overlay").style.display = "block";
    });
    $(document).on('click', '.close-quick-edit', function(e) {
        e.preventDefault();
        $(this).parents('div.sidenav').css("width", 0);
        document.getElementById("main").style.marginLeft = "0";
        document.getElementById("overlay").style.display = "none";
    });
    $(document).on('click', 'div#overlay', function(e) {
        e.preventDefault();
        $('div.sidenav').css("width", 0);
        document.getElementById("main").style.marginLeft = "0";
        document.getElementById("overlay").style.display = "none";
    });
    $('select#rpress_filter_order_date').on('change', function() {
        $('div.rpress-date-filters').removeClass('show-filters');
        if ( this.value === 'yesterday' ) {
            var d = new Date();
            var strDate = (d.getMonth()+1) + "/" + (d.getDate()-1) + "/" + d.getFullYear();
            $('div.rpress-date-filters').find('#start-date').val(strDate);
            $('div.rpress-date-filters').find('#end-date').val('');
            $('div.rpress-date-filters').find('#btn_submit').trigger('click');
        }
        if ( this.value === '7days' ) {
            var d = new Date();
            var date = new Date(new Date().setDate(new Date().getDate() - 7));
            var strDate = (date.getMonth()+1) + "/" + date.getDate() + "/" + date.getFullYear();
            var endDate = (d.getMonth()+1) + "/" + d.getDate() + "/" + d.getFullYear();
            $('div.rpress-date-filters').find('#start-date').val(strDate);
            $('div.rpress-date-filters').find('#end-date').val(endDate);
            $('div.rpress-date-filters').find('#btn_submit').trigger('click');
        }
        if ( this.value === '30days' ) {
            var d = new Date();
            var date = new Date(new Date().setDate(new Date().getDate() - 30));
            var strDate = (date.getMonth()+1) + "/" + date.getDate() + "/" + date.getFullYear();
            var endDate = (d.getMonth()+1) + "/" + d.getDate() + "/" + d.getFullYear();
            $('div.rpress-date-filters').find('#start-date').val(strDate);
            $('div.rpress-date-filters').find('#end-date').val(endDate);
            $('div.rpress-date-filters').find('#btn_submit').trigger('click');
        }
        if ( this.value === 'today' ) {
            var d = new Date();
            var strDate = (d.getMonth()+1) + "/" + d.getDate() + "/" + d.getFullYear();
            $('div.rpress-date-filters').find('#start-date').val(strDate);
            $('div.rpress-date-filters').find('#end-date').val('');
            $('div.rpress-date-filters').find('#btn_submit').trigger('click');
        }
        if ( this.value === 'startend' ) {
            $('div.rpress-date-filters').addClass('show-filters');
        }
    });
    $('select#order_status').on('change', function() {
        setTimeout(() => {
            $('div.rpress-date-filters').find('#btn_submit').trigger('click');
        }, 100);
    });
});