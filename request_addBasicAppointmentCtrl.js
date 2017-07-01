'use strict';
/*jslint eqeq: true*/

angular.module('pulse')
    .controller('addBasicAppointment', ['$scope', 'CompanyAppointments', 'toastr', '$moment', '$state', '$modal', 'config', 'TimezoneService', 'Auth','$rootScope',
        function ($scope, CompanyAppointments, toastr, $moment, $state, $modal, config, TimezoneService, Auth, $rootScope) {
            $scope.days = ['Sunday', 'Monday', 'Tuesday', 'Wednesday', 'Thursday', 'Friday', 'Saturday'];
            $scope.appointment = new CompanyAppointments();
            $scope.appointment.expert_id = $state.params.expert_id;
            $scope.appointment.appointment_type = 'basic';             
           // $scope.appointment.appointment_date = $moment($state.params.selected_date).format('ddd, MMM D, YYYY');
            $scope.appointment.appointment_date = $moment($state.params.selected_date).format('YYYY-MM-DD');
            $scope.appointment.start_time = $moment($scope.appointment.appointment_date + ' ' + $state.params.selected_time, 'YYYY-MM-DD hh:mm a');
            $scope.appointment.end_time = $moment($scope.appointment.appointment_date + ' ' + $state.params.selected_time, 'YYYY-MM-DD hh:mm a').add(1, 'hour');
            $scope.datepickers = {};
            $scope.open = function ($event, property) {
                $event.preventDefault();
                $event.stopPropagation();
                $scope.datepickers[property] = true;
            };
            $scope.appointment.day = [];
            $scope.appointment.is_repeated = 0;
            $scope.appointment.repeat_end = $moment($scope.appointment.appointment_date, 'YYYY-MM-DD').add(1, 'months').format('YYYY-MM-DD');
                    
            $scope.today = new Date();

            $scope.appointment.added_id = Auth.getCurrentUserId();
            $scope.appointment.created_by = $rootScope.User.company_email ? $rootScope.User.contact_name : $rootScope.User.firstname+' '+$rootScope.User.lastname;
            if (Auth.isAdmin()) {
                $scope.appointment.added_id = 0;                
            }

            $scope.createBasicAppointment = function (form) {
                $scope.appointment.appointment_date = $moment($scope.appointment.appointment_date).format('YYYY-MM-DD');
                var saveBasicAppointmentConfig = angular.copy($scope.appointment),
                    start_time_formatted = TimezoneService.toUserTimezone({
                        date: $moment(saveBasicAppointmentConfig.start_time)
                    }),
                    end_time_formatted = TimezoneService.toUserTimezone({
                        date: $moment(saveBasicAppointmentConfig.end_time)
                    });

                if (!form.$valid) {
                    toastr.error('Empty or invalid fields', 'Error');
                    return;
                }

                saveBasicAppointmentConfig.start_time = $moment(saveBasicAppointmentConfig.start_time).format('HH:mm:ss');
                saveBasicAppointmentConfig.end_time = $moment(saveBasicAppointmentConfig.end_time).format('HH:mm:ss');
                saveBasicAppointmentConfig.is_repeated = saveBasicAppointmentConfig.is_repeated == '0' ? 0 : 1;

                if ($scope.appointment.is_repeated != '0') {
                    saveBasicAppointmentConfig.repeat_start = $scope.appointment.appointment_date;
                    saveBasicAppointmentConfig.repeats_on = $scope.appointment.is_repeated;
                }
                else
                {
                    delete saveBasicAppointmentConfig.repeat_end;
                }


                saveBasicAppointmentConfig.day = saveBasicAppointmentConfig.day.join(',');

                CompanyAppointments.query(saveBasicAppointmentConfig)
                    .$promise
                    .then(function (response) {
                        if (response.error) {
                            toastr.error(response.error_msg, 'Error');
                        } else {
                            toastr.success('Your Basic appointment has been added successfully.', 'Success');
                            $rootScope.saveData = 1;
                            $state.transitionTo('common.calendar', {
                                expert_id: $scope.appointment.expert_id,
                                date: $moment($scope.appointment.appointment_date).format('YYYY-MM-DD')
                            });
                        }
                    });
            };

            $scope.minDate = new Date();
            $scope.maxDate = $moment().add(6, 'months').format('YYYY-MM-DD');
            $scope.toggleSelection = function toggleSelection(dayname) {
                var idx = $scope.appointment.day.indexOf(dayname);
                // is currently selected
                if (idx > -1) {
                    $scope.appointment.day.splice(idx, 1);
                }
                // is newly selected
                else {
                    $scope.appointment.day.push(dayname);
                }
            }

            $scope.confirmDiscard = function () {
                var confirmDiscardModal = $modal.open({
                    templateUrl: config.views + 'modals/confirmation.html',
                    controller: 'confirmDiscard',
                });
                confirmDiscardModal.result.then(function () {
                    $rootScope.saveData = 1;
                    $state.transitionTo('common.calendar', {
                        expert_id: $scope.appointment.expert_id
                    });
                })
            }

        }
    ]).controller('confirmDiscard', function ($scope, $modalInstance) {
        $scope.title = 'Do you really want to discard?';
        $scope.cancel = function () {
            $modalInstance.dismiss('cancel');
        };
        $scope.confirm = function () {
            $modalInstance.close({});
        }
    });
