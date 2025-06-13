var $ = jQuery;
jQuery('document').ready(function($) {
    if (typeof DataTable !== 'undefined') {
    new DataTable('#user-orders', {
        responsive: true,
        dom: 'frtip',
        order: [[ 0, 'desc' ]],
        initComplete: function () {
            $('#user-orders_filter input').attr('placeholder','Search orders');
        }
    });
}
    $('body .user-dashboard-wrapper.user-profile ul li').click(function(){
        $('body .user-dashboard-wrapper.user-profile ul li').removeClass('active');
        $(this).addClass('active');
    });
    $('body .user-dashboard-wrapper.user-profile ul li.user-profile').click(function(){
        $('body .user-dashboard-wrapper.user-profile #user-profile').show();
        $('body .user-dashboard-wrapper.user-profile #user-my-address').hide();
        $('body .user-dashboard-wrapper.user-profile #user-my-orders').hide();
    });
    $('body .user-dashboard-wrapper.user-profile ul li.user-my-address').click(function(){
        $('body .user-dashboard-wrapper.user-profile #user-profile').hide();
        $('body .user-dashboard-wrapper.user-profile #user-my-address').show();
        $('body .user-dashboard-wrapper.user-profile #user-my-orders').hide();
    });
    $('body .user-dashboard-wrapper.user-profile ul li.user-my-orders').click(function(){
        $('body .user-dashboard-wrapper.user-profile #user-profile').hide();
        $('body .user-dashboard-wrapper.user-profile #user-my-address').hide();
        $('body .user-dashboard-wrapper.user-profile #user-my-orders').show();
    });
    $('body .user-profile .delete-address').click(function(e){
        e.preventDefault();
        var index = $(this).data('index');
        
        $.ajax({
            url: users.ajaxurl,
            type: 'POST',
            data: {
                index: index,
                action: 'rpress_delete_user_address'
            },
            success: function(response){
                alert('Address Deleted.');
                location.reload();
            },
            error: function(){
                alert('Error occurred while processing your request.');
            }
        });
    });
    $('body .user-profile .default-address').click(function(e) {
        e.preventDefault();
        var index = $(this).data('index');
        
        $.ajax({
            url: users.ajaxurl,
            type: 'POST',
            data: {
                index: index,
                action: 'rpress_default_user_address'
            },
            success: function(response){
                location.reload();
            },
    
        });
    });
    $('.sidebar-menu li').click(function(){
        var clickedClass = $(this).attr('class');
        clickedClass = clickedClass.replace(/\bactive\b/, '').trim();
        var expirationDate = new Date();
        expirationDate.setTime(expirationDate.getTime() + (5 * 60 * 1000));
        Cookies.set('nav_location', clickedClass,{ expires: expirationDate });
    });
    var navLocation = Cookies.get('nav_location');
    if (navLocation) {
        $('body .user-dashboard-wrapper.user-profile ul li').removeClass('active');
        $('body .user-dashboard-wrapper.user-profile ul li.'+navLocation).addClass('active');
        if(navLocation == 'user-profile'){
            $('body .user-dashboard-wrapper.user-profile #user-profile').show();
            $('body .user-dashboard-wrapper.user-profile #user-my-address').hide();
            $('body .user-dashboard-wrapper.user-profile #user-my-orders').hide();
        }
        else if(navLocation == 'user-my-orders'){
            $('body .user-dashboard-wrapper.user-profile #user-profile').hide();
            $('body .user-dashboard-wrapper.user-profile #user-my-address').hide();
            $('body .user-dashboard-wrapper.user-profile #user-my-orders').show();
        }             
        else if(navLocation == 'user-my-address'){
            $('body .user-dashboard-wrapper.user-profile #user-profile').hide();
            $('body .user-dashboard-wrapper.user-profile #user-my-address').show();
            $('body .user-dashboard-wrapper.user-profile #user-my-orders').hide();
        }
            
    }   
});
function addaddress() {
    var div = document.getElementById("add-address-bg");
    if (div.className === "") {
        div.className = "active";
    } else {
        div.className = "";
    }
}
function editaddress(event) {
    var div = document.getElementById("add-address-bg");
    if (div.className === "") {
        div.className = "active";
    } else {
        div.className = "";
    }
    // Find the nearest parent element with class 'address-wrap'
    var addressWrap = $(event.target).closest('.address-wrap');
    console.log(addressWrap);
    
    // Get the values within the nearest 'address-wrap' element
    var addressType     = addressWrap.find('.type-of-address').text();
    var firstname       = addressWrap.find('.user-firstname').text();
    var lastname        = addressWrap.find('.user-lastname').text();
    var phone           = addressWrap.find('.user-contact').text();
    var pincode         = addressWrap.find('.user-pin').text();
    var city            = addressWrap.find('.user-city').text();
    var address         = addressWrap.find('.user-street-address').text();
    var apartment       = addressWrap.find('.user-apt-suite').text();
    var addressIndex    = addressWrap.find('.user-address-index').text();
    var defaultIndex    = addressWrap.find('.user-default-address').text();
    
    // Example: Update a form with the retrieved values
    $('#add-address-bg .box-header .box-title').text("Edit Delivery Address");
    $('#form_submit_button').val('Save Changes');
    $('#addressTypeInput').val(addressType);
    $('#fastNameInput').val(firstname);
    $('#lastNameInput').val(lastname);
    $('#phoneInput').val(phone);
    $('#pincodeInput').val(pincode);
    $('#cityAddress').val(city);
    $('#streetaddress').val(address);
    $('#aptsuite').val(apartment);
    $('#edit_user_address_index').val(addressIndex);
    $('#default-address-checkboxInput6').prop('checked', defaultIndex);
}