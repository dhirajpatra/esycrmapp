(function($) {
    "use strict"; // Start of use strict

    // data tables ready
    $(document).ready(function() {
        if ($('.datatable').length === 0) {
            $('.datatable').DataTable({
                "bAutoWidth": true,
                "bPaginate": true,
                "bLengthChange": true,
                "bInfo": false,
            });
        }
    });

    // delete a contact along with its address
    $(document).on('submit', "#frm_super_company_status_update", function(event) {
        event.preventDefault(); //prevent default action 
        var r = confirm("Are you sure to update the status of this company?");
        if (r != true) {
            return false;
        }

        var post_url = $(this).attr("action"); //get form action url
        var request_method = $(this).attr("method"); //get form GET/POST method
        var form_data = $(this).serialize(); //Encode form elements for submission

        $.ajax({
            url: post_url,
            type: request_method,
            data: form_data
        }).done(function(response) { //    
            if (response == '200') {
                // console.log('200');
                // need to reload the page
                $('#super_company_result').toggleClass('alert-success');
                $('#super_company_result').html('Company status updated sucessfully');
                window.setTimeout(function() {
                    location.reload();
                }, 2000);

            } else if (response == '409') {
                // console.log('409');
                $('#super_company_result').toggleClass('alert-danger');
                $('#super_company_result').html('Company status not udpated');
                return false;
            } else {
                // console.log('400');
                $('#super_company_result').toggleClass('alert-danger');
                $('#super_company_result').html('Company status not updated');
                return false;
            }
        });
    });

    // submit the mail update
    $(document).on('submit', ".frm_super_mail_update", function(event) {
        event.preventDefault(); //prevent default action 

        var post_url = $(this).attr("action"); //get form action url
        var request_method = $(this).attr("method"); //get form GET/POST method
        var form_data = $(this).serialize(); //Encode form elements for submission
        var id = '';
        var current_body = '';
        var temp = form_data.split('&');
        $(temp).each(function(k, v) {
            var element = v.split('=');
            if (element[0] == 'id') {
                id = element[1];
            } else if (element[0] == 'current_body') {
                current_body = element[1].trim();
            }
        });

        // need to check that existing body updated or not
        var body = eval('CKEDITOR.instances.body_' + id + '.getData()').trim();
        // no change
        if (body == current_body) {
            return false;
        }

        $.ajax({
            url: post_url,
            type: request_method,
            data: form_data
        }).done(function(response) { //    
            if (response == '200') {
                // console.log('200');
                // need to reload the page
                $('#super_company_result').toggleClass('alert-success');
                $('#super_company_result').html('Mail updated sucessfully');
                window.setTimeout(function() {
                    location.reload();
                }, 2000);

            } else if (response == '409') {
                // console.log('409');
                $('#super_company_result').toggleClass('alert-danger');
                $('#super_company_result').html('Mail not udpated');
                return false;
            } else {
                // console.log('400');
                $('#super_company_result').toggleClass('alert-danger');
                $('#super_company_result').html('Mail not updated');
                return false;
            }
        });
    });

    // add a new mail module
    $(document).on('submit', "#frm_add_mail_module", function(event) {
        event.preventDefault(); //prevent default action 

        var post_url = $(this).attr("action"); //get form action url
        var request_method = $(this).attr("method"); //get form GET/POST method
        var form_data = $(this).serialize(); //Encode form elements for submission

        $.ajax({
            url: post_url,
            type: request_method,
            data: form_data
        }).done(function(response) { //    
            if (response == '200') {
                // console.log('200');
                // need to reload the page
                $('#super_company_result').toggleClass('alert-success');
                $('#super_company_result').html('Mail added sucessfully');
                window.setTimeout(function() {
                    location.reload();
                }, 2000);

            } else if (response == '409') {
                // console.log('409');
                $('#super_company_result').toggleClass('alert-danger');
                $('#super_company_result').html('Mail not added');
                return false;
            } else {
                // console.log('400');
                $('#super_company_result').toggleClass('alert-danger');
                $('#super_company_result').html('Mail not added');
                return false;
            }
        });
    });

    // send bulk mails
    $(document).on('submit', "#frm_super_bulk_mails", function(event) {
        event.preventDefault(); //prevent default action 

        var post_url = $(this).attr("action"); //get form action url
        var request_method = $(this).attr("method"); //get form GET/POST method
        var form_data = $(this).serialize(); //Encode form elements for submission

        $.ajax({
            url: post_url,
            type: request_method,
            data: form_data
        }).done(function(response) { //    
            if (response == '200') {
                // console.log('200');
                // need to reload the page
                $('#super_company_result').toggleClass('alert-success');
                $('#super_company_result').html('Mails sent sucessfully');
                window.setTimeout(function() {
                    location.reload();
                }, 2000);

            } else if (response == '409') {
                // console.log('409');
                $('#super_company_result').toggleClass('alert-danger');
                $('#super_company_result').html('Mails not sent');
                return false;
            } else {
                // console.log('400');
                $('#super_company_result').toggleClass('alert-danger');
                $('#super_company_result').html('Mails not sent');
                return false;
            }
        });
    });

    // search 
    $('.ui.search')
        .search({
            type: 'category',
            minCharacters: 3,
            apiSettings: {
                onResponse: function(serverResponse) {
                    // console.log(serverResponse);
                    var
                        response = {
                            results: {}
                        };
                    // translate response to work with search
                    $.each(serverResponse, function(index, val) {
                        response.results[index] = {
                            name: index,
                            results: []
                        };
                        // for which module deals, contacts or tasks
                        $.each(val, function(key, item) {
                            // to check array is empty or not
                            if (typeof item.id !== 'undefined') {
                                // add result to module
                                if (index == 'Companies') {
                                    response.results[index].results.push({
                                        id: item.id,
                                        title: item.company_name,
                                        description: item.email,
                                        url: '/manager/super/companies'
                                    });
                                } else if (index == 'Contacts') {
                                    response.results[index].results.push({
                                        id: item.id,
                                        title: item.Contact_First + ' ' + item.Contact_Last,
                                        description: item.Title,
                                        url: (item.role == 1 || item.role == 2) ? '/sales/contacts' : ''
                                    });
                                } else if (index == 'Deals') {
                                    response.results[index].results.push({
                                        id: item.id,
                                        title: item.project_type,
                                        description: item.project_description,
                                        url: (item.role == 1 || item.role == 2) ? '/sales/deals' : ''
                                    });
                                } else if (index == 'Tasks') {
                                    response.results[index].results.push({
                                        id: item.id,
                                        title: item.Notes,
                                        description: item.Todo_Due_Date,
                                        url: (item.role == 1 || item.role == 2) ? '/sales' : ''
                                    });
                                }
                            } else {
                                response.results[index].results.push({
                                    id: '',
                                    title: '',
                                    description: 'No result found',
                                    url: ''
                                });
                            }
                        });
                    });
                    // console.log(response);
                    return response;
                },
                url: '/search/{query}'
            }
        });

    // this will generate chart in dashboard of manager
    $('#myAreaChartSuper').ready(function(event) {

        // ajax call to fetch data
        var post_url = '/manager/super/get_chart_data_for_dashboard'; //get form action url
        var request_method = 'post'; //get form GET/POST method
        var form_data = ''; //Encode form elements for submission

        $.ajax({
            url: post_url,
            type: request_method,
            data: form_data
        }).done(function(response) {
            // to check that it is a json string
            if (response.charAt(0) != '<') {

                response = $.parseJSON(response);
                if (response.status == '200') {
                    // Set new default font family and font color to mimic Bootstrap's default styling
                    Chart.defaults.global.defaultFontFamily = '-apple-system,system-ui,BlinkMacSystemFont,"Segoe UI",Roboto,"Helvetica Neue",Arial,sans-serif';
                    Chart.defaults.global.defaultFontColor = '#292b2c';

                    // Area Chart Example
                    var ctx = document.getElementById("myAreaChartSuper");
                    if (ctx !== null) {
                        ctx = ctx.getContext('2d');
                        // create chart data
                        var dates = new Array();
                        var budgets = new Array();
                        var max_budget = 0;
                        var currency = response.data.currency;
                        $.each(response.data, function(key, value) {
                            if (max_budget < value.budget) {
                                max_budget = parseInt(value.budget);
                            }
                            dates.push(value.created_at);
                            budgets.push(value.budget);
                        });

                        var myLineChart = new Chart(ctx, {
                            type: 'line',
                            data: {
                                labels: dates,
                                datasets: [{
                                    label: "Budget",
                                    lineTension: 0.3,
                                    backgroundColor: "rgba(2,117,216,0.2)",
                                    borderColor: "rgba(2,117,216,1)",
                                    pointRadius: 5,
                                    pointBackgroundColor: "rgba(2,117,216,1)",
                                    pointBorderColor: "rgba(255,255,255,0.8)",
                                    pointHoverRadius: 5,
                                    pointHoverBackgroundColor: "rgba(2,117,216,1)",
                                    pointHitRadius: 50,
                                    pointBorderWidth: 2,
                                    data: budgets,
                                }],
                            },
                            options: {
                                scales: {
                                    xAxes: [{
                                        time: {
                                            unit: 'date'
                                        },
                                        gridLines: {
                                            display: false
                                        },
                                        ticks: {
                                            maxTicksLimit: 15
                                        }
                                    }],
                                    yAxes: [{
                                        ticks: {
                                            min: 0,
                                            max: max_budget,
                                            maxTicksLimit: 15
                                        },
                                        gridLines: {
                                            color: "rgba(0, 0, 0, .125)",
                                        }
                                    }],
                                },
                                legend: {
                                    display: true
                                }
                            }
                        });

                    } else {
                        var response = 'No data found.';
                        // console.log(response);
                    }
                }
            }
        });
    });

    // this will generate chart in dashboard of manager from submit date range
    $('#myAreaChartSuperPost').ready(function(event) {
        var pathname = window.location.pathname;
        // posting different date range call for chart
        if (pathname == '/manager/super/analytics') {
            // ajax call to fetch data
            var post_url = '/manager/super/get_chart_data_for_dashboard'; //get form action url
            var request_method = 'post'; //get form GET/POST method
            var form_data = ''; //Encode form elements for submission

            $.ajax({
                url: post_url,
                type: request_method,
                data: form_data
            }).done(function(response) {
                // to check that it is a json string
                if (response.charAt(0) != '<') {

                    response = $.parseJSON(response);
                    if (response.status == '200') {
                        // Set new default font family and font color to mimic Bootstrap's default styling
                        Chart.defaults.global.defaultFontFamily = '-apple-system,system-ui,BlinkMacSystemFont,"Segoe UI",Roboto,"Helvetica Neue",Arial,sans-serif';
                        Chart.defaults.global.defaultFontColor = '#292b2c';

                        // Area Chart Example
                        var ctx = document.getElementById("myAreaChartSuperPost");
                        if (ctx !== null) {
                            ctx = ctx.getContext('2d');
                            // create chart data
                            var dates = new Array();
                            var budgets = new Array();
                            var max_budget = 0;
                            var currency = response.data.currency;
                            $.each(response.data, function(key, value) {
                                if (max_budget < value.budget) {
                                    max_budget = parseInt(value.budget);
                                }
                                dates.push(value.created_at);
                                budgets.push(value.budget);
                            });

                            var myLineChart = new Chart(ctx, {
                                type: 'line',
                                data: {
                                    labels: dates,
                                    datasets: [{
                                        label: "Budget",
                                        lineTension: 0.3,
                                        backgroundColor: "rgba(2,117,216,0.2)",
                                        borderColor: "rgba(2,117,216,1)",
                                        pointRadius: 5,
                                        pointBackgroundColor: "rgba(2,117,216,1)",
                                        pointBorderColor: "rgba(255,255,255,0.8)",
                                        pointHoverRadius: 5,
                                        pointHoverBackgroundColor: "rgba(2,117,216,1)",
                                        pointHitRadius: 50,
                                        pointBorderWidth: 2,
                                        data: budgets,
                                    }],
                                },
                                options: {
                                    scales: {
                                        xAxes: [{
                                            time: {
                                                unit: 'date'
                                            },
                                            gridLines: {
                                                display: false
                                            },
                                            ticks: {
                                                maxTicksLimit: 15
                                            }
                                        }],
                                        yAxes: [{
                                            ticks: {
                                                min: 0,
                                                max: max_budget,
                                                maxTicksLimit: 15
                                            },
                                            gridLines: {
                                                color: "rgba(0, 0, 0, .125)",
                                            }
                                        }],
                                    },
                                    legend: {
                                        display: true
                                    }
                                }
                            });

                        } else {
                            response = 'No data found.';
                            console.log(response);
                        }
                    }
                }
            });
        }
    });

})(jQuery); // End of use strict