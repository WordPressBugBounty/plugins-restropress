jQuery(document).ready(function($) {
    let tabs = $('.tab');
    let currentTab = 0;
    let prevButton = $('#prevBtn');
    let nextButton = $('#nextBtn');
    let skiptext = $('#skiptext');
    let finish = $('.finish-btn');

    showTab(currentTab);

    function showTab(n) {
        tabs.hide(); // Hide all tabs
        $(tabs[n]).show(); // Show current

        // Handle buttons
        if (n === 0) {
            prevButton.hide();
        } else {
            prevButton.show();
        }

        if (n === (tabs.length - 1)) {
            nextButton.hide();
            prevButton.hide();
            skiptext.text('Finish.');
            finish.show();
        } else {
            nextButton.text('Next').show();
            finish.hide();
        }

        // Update step number
        $('.current-page-num').text(n + 1);
        fixStepIndicator(n);
    }

    $('body').on('click', '.next-prev-btn', function() {
        const step = parseInt($(this).attr('nextTab'));
        if (isNaN(step)) return;

        $(tabs[currentTab]).hide();
        currentTab += step;

        if (currentTab >= tabs.length) currentTab = tabs.length - 1;
        if (currentTab < 0) currentTab = 0;

        showTab(currentTab);
    });

    function fixStepIndicator(n) {
        $('.multiStep__circles .step').removeClass('active');
        for (let i = 0; i <= n; i++) {
            $('.multiStep__circles .step').eq(i).addClass('active');
        }
    }
});

/*file upload*/
function readURL(input) {
  if (input.files && input.files[0]) {

    var reader = new FileReader();

    reader.onload = function(e) {
    //   $('.image-upload-wrap').hide();

      $('.file-upload-image').attr('src', e.target.result);
      $('.file-upload-content').show();

      $('.image-title').html(input.files[0].name);
    };

    reader.readAsDataURL(input.files[0]);

  } else {
    removeUpload();
  }
}

function removeUpload() {
  $('.file-upload-input').replaceWith($('.file-upload-input').clone());
  $('.file-upload-content').hide();
  $('.image-upload-wrap').show();
}
$('.image-upload-wrap').bind('dragover', function () {
    $('.image-upload-wrap').addClass('image-dropping');
  });
  $('.image-upload-wrap').bind('dragleave', function () {
    $('.image-upload-wrap').removeClass('image-dropping');
});