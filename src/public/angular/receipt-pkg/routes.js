app.config(['$routeProvider', function($routeProvider) {

    $routeProvider.
    //CUSTOMER
    when('/receipt-pkg/receipt/list', {
        template: '<receipt-list></receipt-list>',
        title: 'Receipts',
    }).
    when('/receipt-pkg/receipt/add', {
        template: '<receipt-form></receipt-form>',
        title: 'Add Receipt',
    }).
    when('/receipt-pkg/receipt/edit/:id', {
        template: '<receipt-form></receipt-form>',
        title: 'Edit Receipt',
    });
}]);