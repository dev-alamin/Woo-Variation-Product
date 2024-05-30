jQuery(document).ready(function($) {
var previousVariationValue = '';
var selectedFlavour = $('.selected_flavour');

if (selectedFlavour.is(':empty')) {
    selectedFlavour.text('Choose a flavour');
}

function updateURL(attributeId) {
    var selectElement = $('#' + attributeId);

    selectElement.on('change', function() {
        // Get the variation value
        var variationValue = $(this).find('option:selected').text().trim();

        // Make the variation value URL-friendly
        variationValue = variationValue.toLowerCase()
            .replace(/\s+/g, '-')           // Replace spaces with hyphens
            .replace(/[(){}\[\]&]/g, '')    // Remove parentheses, brackets, and ampersand
            .replace(/-+/g, '-');           // Replace multiple hyphens with a single hyphen

        var currentURL = window.location.href;
        var hasTrailingSlash = currentURL.endsWith('/');
        var urlParts = currentURL.split('/');
        var lastPartIndex = urlParts.length - 1;

        // Remove empty parts at the end of the array
        while (lastPartIndex >= 0 && urlParts[lastPartIndex] === '') {
            urlParts.splice(lastPartIndex, 1);
            lastPartIndex--;
        }

        // Update the last part of the URL or append the new value
        if (urlParts.length >= 5) {
            urlParts[urlParts.length - 1] = variationValue;
        } else {
            urlParts.push(variationValue);
        }

        var updatedURL = urlParts.join('/') + (hasTrailingSlash ? '/' : '');
        history.pushState({}, '', updatedURL);
    });
}

updateURL('pa_flavour');
updateURL('pa_product-colour');

});

// Copy text 
jQuery(document).ready(function($) {
// Function to copy text from one element to another
function copyVariationDescription() {
    var variationDescription = $('.single-product-details .with-flavour-picker .woocommerce-variation-description').html();
    if (variationDescription) {
        $('#tab-description').html('');
        $('#tab-description').html(variationDescription);
    }
}

// Trigger the function when a variation is selected
$('.variations_form').on('show_variation', function(event, variation) {
    copyVariationDescription();
    copyFlavourToAdditionalInfo();
});

// Function to copy text from the selected flavour to the additional information tab
function copyFlavourToAdditionalInfo() {
    var selectedFlavour = $('.single-product .selected_flavour').text();
    $('.woocommerce-product-attributes-item--attribute_pa_flavour .woocommerce-product-attributes-item__value').html('<p>' + selectedFlavour + '</p>');
}

// Trigger the function on click of the additional information tab link
$('#tab-title-additional_information a').on('click', function(event) {
    event.preventDefault(); // Prevent default anchor behavior
    copyFlavourToAdditionalInfo();
});

// Trigger the function on page load in case a variation is pre-selected
// copyVariationDescription();
});