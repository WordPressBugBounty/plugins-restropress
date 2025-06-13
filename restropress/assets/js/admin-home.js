jQuery(document).ready(function($){
    // tab_js 
    let tabs = document.querySelectorAll('.tab');
    let currentTab = 0;
    let prevButton = document.querySelector('#prevBtn');
    let nextButton = document.querySelector('#nextBtn');
    let skiptext = document.querySelector('#skiptext');
    let finish = $('input.button.button-primary.next-prev-btn');
    
    showTab(currentTab);
    function showTab(n) {
        // Select tab
        
        tabs[n].style.display = 'flex';
        // Fix Buttons
        if (n == 0) {
            prevButton.style.display = 'none';
        } else {
            prevButton.style.display = 'inline-block';
        }
        // Fix next Button
        if ( n == (tabs.length - 1 )) {
            nextButton.innerHTML = 'Finish';   
            nextButton.disabled = true;
            prevButton.style.display = 'none';
            nextButton.style.display = 'none';
            skiptext.innerHTML = 'Finish.';
            skiptext.disabled = true;
            finish.style.display = 'flex';
        } else {
            nextButton.innerHTML = 'Next';
            finish.style.display = 'none';
        }
        let pageNumber = parseInt( n + 1 );
        document.querySelector('.current-page-num').innerHTML = pageNumber;   
        fixStepIndicator(n)
    };
    $('body').on('click', '.next-prev-btn', function() {
        var n = parseInt( $(this).attr('nextTab') );
        tabs[currentTab].style.display = 'none';
        currentTab = parseInt( currentTab + n );
        showTab(currentTab);
    });
    function fixStepIndicator(n) {
        jQuery('.multiStep__circles .step').removeClass('active');
        for (let i = 0 ; i <= n; i++) {
            jQuery(`.multiStep__circles .step:eq(${i})`).addClass('active');
    }
    }
});