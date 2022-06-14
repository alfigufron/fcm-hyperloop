<?php

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| API Routes
|--------------------------------------------------------------------------
|
|ello  Here is where you can register API routes for your application. These
| routes are loaded by the RouteServiceProvider within a group which
| is assigned the "api" middleware group. Enjoy building your API!
|
*/

Route::middleware('auth:api')->get('/user', function (Request $request) {
    return $request->user();
});

Route::middleware('jwt:superadmin')->group(function(){
    Route::get('/test', 'TestController@index');
});

/**
 * AUTH
 *
 */

Route::namespace('Auth')->prefix('auth')->group(function(){
    Route::post('/register', 'LoginController@register');
    Route::post('/login', 'LoginController@login');
    Route::post('/forgot-password', 'ResetPasswordController@forgot');
    Route::post('/reset-password', 'ResetPasswordController@reset');
});

/**
 * JITSI MEET HANG UP
 *
 */
Route::post('/jitsi-meet/study-session/hang-up', 'JitsiMeetController@hangUp');

/**
 * PAYMENT
 *
 */
Route::middleware('jwt')->prefix('payment')->group(function(){
    Route::get('/methods', 'PaymentController@methods');
    Route::post('/pay', 'PaymentController@pay');
});

/**
 * TEACHER
 *
 */
Route::get('/dump', 'TestController@index');
Route::middleware('jwt:teacher')->prefix('teacher')->group(function(){
    // Report
    Route::prefix('report')->group(function(){
        Route::get('/assignment/{assignment_id}', 'ReportController@TeacherAssignmentToStudent');
        Route::get('/student_replacement/{replacement_id}', 'ReportController@StudentReplacement');
        Route::get('/student_remedial/{assignment_id}', 'ReportController@StudentRemedial');
    });

    /**
     * TEACHER CLASSROOM
     *
     */
    Route::prefix('classroom')->group(function(){
        Route::get('/', 'TeacherClassroomController@index');
        Route::get('/create/grade', 'TeacherClassroomController@createSelectGrade');
        Route::get('/create/classroom', 'TeacherClassroomController@createSelectClassroom');
        Route::get('/create/additional-student', 'TeacherClassroomController@additionalStudent');
        Route::post('/create', 'TeacherClassroomController@create');
        Route::get('/update/detail', 'TeacherClassroomController@updateDetail');
        Route::put('/update', 'TeacherClassroomController@update');
        Route::delete('/', 'TeacherClassroomController@delete');
        Route::get('/detail', 'TeacherClassroomController@detail');
        Route::get('/next', 'TeacherClassroomController@lessonNext');
    });

    /**
     * TEACHER LESSON
     *
     */
    Route::prefix('lesson')->group(function(){
        Route::get('/next', 'TeacherLessonController@next');
        Route::get('/list', 'TeacherLessonController@list');
        Route::get('/task', 'TeacherLessonController@task');
        Route::get('/test', 'TeacherLessonController@test');
        Route::get('/detail', 'TeacherLessonController@detail');
        Route::get('/create/subjects', 'TeacherLessonController@selectSubjects');
        Route::post('/create', 'TeacherLessonController@create');
        Route::put('/', 'TeacherLessonController@update');
        Route::delete('/', 'TeacherLessonController@delete');
    });

    /**
     * TEACHER LIBRARY
     */
    Route::prefix('/library')->group(function (){
        /**
         * TEACHER QUESTION BANK
         */
        Route::prefix('question-bank')->group(function (){
            Route::get('/grade', 'TeacherQuestionBankController@grade');
            Route::get('/semester', 'TeacherQuestionBankController@semester');
            Route::get('/subject', 'TeacherQuestionBankController@subject');
            Route::get('/search', 'TeacherQuestionBankController@search');
            Route::get('/header', 'TeacherQuestionBankController@header');
            Route::get('/list', 'TeacherQuestionBankController@list');
            Route::get('/detail', 'TeacherQuestionBankController@detail');
            Route::get('/media', 'TeacherQuestionBankController@media');
            Route::post('/media/upload', 'TeacherQuestionBankController@mediaUpload');
            Route::put('/media/question-item/update', 'TeacherQuestionBankController@updateMedia');
            Route::delete('/media/question-item/delete', 'TeacherQuestionBankController@deleteMedia');
            Route::put('/media/question-item-answer/update', 'TeacherQuestionBankController@updateMedia');
            Route::delete('/media/question-item-answer/delete', 'TeacherQuestionBankController@deleteMedia');
            Route::put('/media/question-item-discuss/update', 'TeacherQuestionBankController@updateMedia');
            Route::delete('/media/question-item-discuss/delete', 'TeacherQuestionBankController@deleteMedia');
            Route::post('/upload', 'TeacherQuestionBankController@upload');
            Route::delete('/delete', 'TeacherQuestionBankController@deleteQuestion');
        });

        /**
         * TEACHER LEARNING MATERIAL
         */
        Route::prefix('learning-material')->group(function (){
            Route::get('/grade', 'TeacherLearningMaterialController@grade');
            Route::get('/semester', 'TeacherLearningMaterialController@semester');
            Route::get('/subjects', 'TeacherLearningMaterialController@subject');
            Route::get('/header', 'TeacherLearningMaterialController@header');
            Route::get('/list', 'TeacherLearningMaterialController@list');
            Route::post('/upload', 'TeacherLearningMaterialController@upload');
            Route::get('/create/subjects', 'TeacherLearningMaterialController@createSubject');
            Route::get('/create/main-topic', 'TeacherLearningMaterialController@createMainTopic');
            Route::post('/create', 'TeacherLearningMaterialController@create');
            Route::get('/detail', 'TeacherLearningMaterialController@detail');
        });
    });

    /**
     * TEACHER PROFILE
     *
     */
    Route::prefix('profile')->group(function(){
        Route::get('/detail', 'TeacherProfileController@detail');
        Route::get('/classrooms', 'TeacherProfileController@profileClassrooms');
        Route::get('/subjects', 'TeacherProfileController@profileSubjects');
        Route::get('/students', 'TeacherProfileController@profileStudents');
        Route::get('/attendance-history/detail', 'TeacherProfileController@attendanceHistoryStudentDetail');
        Route::get('/learning-progress/detail', 'TeacherProfileController@learningProgressStudentDetail');
        Route::get('/progress/report/{teacher_id}', 'TeacherProgressReportController@index');
        Route::get('/attendance/report/{teacher_id}', 'TeacherAttendanceReportController@index');
        Route::get('/student/attendance/report/{id}', 'StudentAttendanceReportController@index');
    });

    /**
     * TEACHER FORUM
     */
    Route::prefix('forum')->group(function (){
        Route::get('/info', 'TeacherForumController@info');
        Route::get('/today', 'TeacherForumController@today');
        Route::get('/list', 'TeacherForumController@list');
        Route::put('/', 'TeacherForumController@update');
        Route::delete('/', 'TeacherForumController@delete');


        /**
         * Teacher Learning Contract & Class Replacement
         */
        Route::get('/semester', 'TeacherForumController@semester');

        /**
         * Teacher Forum Learning Contract
         */
        Route::prefix('learning-contract')->group(function (){
            Route::get('/header', 'TeacherForumLearningContractController@header');
            Route::get('/detail', 'TeacherForumLearningContractController@detail');
        });

        /**
         * Teacher Forum Class replacement
         */
        Route::prefix('class-replacement')->group(function (){
            Route::get('/header', 'TeacherForumClassReplacementController@header');
            Route::get('/detail', 'TeacherForumClassReplacementController@detail');
        });

        /**
         * Teacher Forum Class Attendance
         */
        Route::prefix('class-attendance')->group(function (){
            Route::get('/header', 'TeacherForumClassAttendanceController@header');
            Route::get('/' , 'TeacherForumClassAttendanceController@index');
            Route::get('/info', 'TeacherForumClassAttendanceController@info');
            Route::get('/main-topic', 'TeacherForumClassAttendanceController@mainTopic');
            Route::get('/sub-topic', 'TeacherForumClassAttendanceController@subTopic');
            Route::get('/submit-topic', 'TeacherForumClassAttendanceController@submitTopic');
            Route::post('/upload', 'TeacherForumClassAttendanceController@uploadMedia');
            Route::get('/teacher-attendance', 'TeacherForumClassAttendanceController@teacherAttendance');
            Route::post('/teacher-attendance', 'TeacherForumClassAttendanceController@teacherSubmitAttendance');
            Route::get('/attendance', 'TeacherForumClassAttendanceController@attendance');
            Route::post('/attendance', 'TeacherForumClassAttendanceController@submitattendance');
            Route::get('/replacement', 'TeacherForumClassAttendanceController@replacement');
            Route::post('/replacement', 'TeacherForumClassAttendanceController@submitreplacement');
        });

        /**
         * Teacher Forum Test and Task
         */
        Route::prefix('work')->group(function (){
            Route::get('/', 'TeacherForumWorkControllers@list');
        });

        /**
         * Teacher Forum Test
         */
        Route::prefix('test')->group(function (){
            Route::get('/header', 'TeacherForumTestController@header');
            Route::get('/', 'TeacherForumTestController@index');
            Route::delete('/', 'TeacherForumTestController@delete');
            Route::post('/upload', 'TeacherForumTestController@upload');
            Route::get('/create/main-topic', 'TeacherForumTestController@learningMaterial');
            Route::get('/create/students', 'TeacherForumTestController@students');
            Route::post('/create', 'TeacherForumTestController@create');
            Route::get('/detail', 'TeacherForumTestController@detail');
            Route::put('/detail', 'TeacherForumTestController@edit');
            Route::prefix('/progress')->group(function (){
                Route::get('/', 'TeacherForumTestController@progress');
                Route::get('/info', 'TeacherForumTestController@progressinfo');
                Route::get('/detail', 'TeacherForumTestController@progressdetail');
                Route::get('/student-detail', 'TeacherForumTestController@progresstudent');
                Route::post('/student-detail', 'TeacherForumTestController@inputscorestudent');
            });
            Route::get('/input', 'TeacherForumTestController@inputTest');
            Route::get('/scoring', 'TeacherForumTestController@testScoring');
            Route::post('/submit-scoring', 'TeacherForumTestController@submitTestScoring');

        });

        /**
         * Teacher Forum Task
         */
        Route::prefix('/task')->group(function (){
            Route::get('/header', 'TeacherForumTaskController@header');
            Route::get('/', 'TeacherForumTaskController@taskList');
            Route::delete('/', 'TeacherForumTaskController@delete');
            Route::get('/edit/detail', 'TeacherForumTaskController@taskEdit');
            Route::post('/edit/', 'TeacherForumTaskController@submitTaskEdit');
            Route::get('/create/main-topic', 'TeacherForumTaskController@learningMaterial');
            Route::get('/detail', 'TeacherForumTaskController@detail');
            Route::post('/create', 'TeacherForumTaskController@create');
            Route::post('/upload', 'TeacherForumTaskController@upload');
            Route::get('/detail', 'TeacherForumTaskController@detail');
            Route::prefix('/progress')->group(function (){
                Route::get('/', 'TeacherForumTaskController@progress');
                Route::get('/info', 'TeacherForumTaskController@progressInfo');
                Route::get('/detail', 'TeacherForumTaskController@progressDetail');
                Route::get('/student-detail', 'TeacherForumTaskController@progressStudent');
                Route::post('/student-detail', 'TeacherForumTaskController@inputScoresStudent');
            });
        });

        /**
         * Teacher Forum Score
         */
        Route::prefix('score')->group(function (){
            Route::get('/header', 'TeacherForumScoreController@header');
            Route::get('/', 'TeacherForumScoreController@scorelist');
            Route::prefix('test')->group(function (){
                Route::get('/detail', 'TeacherForumScoreController@testdetail');
                Route::get('/detail/info', 'TeacherForumScoreController@testinfo');
                Route::get('/detail/remedial', 'TeacherForumScoreController@testremedial');
                Route::post('/detail/remedial', 'TeacherForumScoreController@submitTestRemedial');
                Route::get('/remedial', 'TeacherForumScoreController@testRemedials');
                Route::post('/submit-remedial', 'TeacherForumScoreController@submitTestRemedial');
            });
            Route::prefix('task')->group(function (){
                Route::get('/detail', 'TeacherForumScoreController@taskdetail');
                Route::get('/detail/info', 'TeacherForumScoreController@taskinfo');
                Route::get('/detail/remedial', 'TeacherForumScoreController@taskremedial');
                Route::post('/detail/remedial', 'TeacherForumScoreController@submitTaskRemedial');
                Route::get('/remedial', 'TeacherForumScoreController@taskRemedials');
                Route::post('/submit-remedial', 'TeacherForumScoreController@submitTaskRemedials');

            });
        });

        /**
         * Teacher Forum Student Member
         */
        Route::prefix('student-member')->group(function (){
            Route::get('/' , 'TeacherForumStudentMemberController@index');
            Route::delete('/' , 'TeacherForumStudentMemberController@delete');
        });
    });
});

/**
 * STUDENT
 *
 */
Route::middleware('jwt:student')->prefix('student')->group(function(){
    /**
     * STUDENT CLASSROOM
     *
     */
    Route::prefix('classroom')->group(function (){
        Route::get('/', 'StudentClassroomController@list');
        Route::get('/detail', 'StudentClassroomController@detail');
        Route::get('/student', 'StudentClassroomController@student');
    });

    /**
     * STUDENT LESSON
     *
     */
    Route::prefix('lesson')->group(function(){
        Route::get('/list', 'StudentLessonController@list');
        Route::get('/next', 'StudentLessonController@next');
    });

    /**
     * STUDENT PROFILE
     *
     */
    Route::prefix('profile')->group(function(){
        Route::get('/detail', 'StudentProfileController@detail');
        Route::get('/classrooms', 'StudentProfileController@classrooms');
        Route::get('/subjects', 'StudentProfileController@subjects');
        Route::get('/learning-progress/detail', 'StudentProfileController@learningProgress');
        Route::get('/attendance-history/detail', 'StudentProfileController@attendanceHistory');
        Route::get('/dashboard', 'StudentProfileController@dashboard');
    });

    /**
     * STUDENT FORUM
     *
     */
    Route::prefix('forum')->group(function(){
        Route::get('/header', 'StudentForumController@header');
        Route::get('/list', 'StudentForumController@list');
        Route::get('/next-meeting', 'StudentForumController@nextMeeting');

        /**
         * STUDENT FORUM LEARNING-CONTRACT & REPLACEMENT-CLASS SEMESTER
         *
         */
        Route::get('/semester', 'StudentForumController@semester');

        /**
         * STUDENT FORUM WORK
         *
         */
        Route::get('/work', 'StudentWorkController@list');

        /**
         * STUDENT FORUM TASK
         *
         */
        Route::prefix('task')->group(function(){
            Route::get('/', 'StudentTaskController@forumTask');
            Route::get('/progress', 'StudentTaskController@forumTaskProgress');
            Route::get('/detail', 'StudentTaskController@forumTaskDetail');
            Route::post('/upload', 'StudentTaskController@forumTaskUpload');
            Route::post('/submit', 'StudentTaskController@forumTaskSubmit');
        });

        /**
         * STUDENT FORUM TEST
         *
         */
        Route::prefix('test')->group(function(){
            Route::get('/', 'StudentTestController@forumTest');
            Route::get('/detail', 'StudentTestController@testDetail');
            // Route::get('/progress', 'StudentTestController@forumTestProgress');
            // Route::post('/start', 'StudentTestController@forumTestStart');
            // Route::get('/assign', 'StudentTestController@forumTestAssign');
            // Route::post('/assign', 'StudentTestController@forumTestAssignProcess');
            Route::post('/upload', 'StudentTestController@forumTestUpload');
            Route::post('/submit', 'StudentTestController@forumTestSubmit');

            // Route::get('/list', 'StudentTestController@listsTest');
            Route::post('/start', 'StudentTestController@startTest');
            Route::get('/progress', 'StudentTestController@progressTest');
            Route::get('/order-progress', 'StudentTestController@orderProgressTest');
            Route::post('/answer', 'StudentTestController@answerTest');
            Route::post('/finish', 'StudentTestController@finishTest');



        });

        /**
         * STUDENT FORUM LEARNING CONTRACT
         *
         */
        Route::prefix('learning-contract')->group(function(){
            Route::get('/', 'StudentLearningContractController@learningContract');
            Route::get('/header', 'StudentLearningContractController@header');
            Route::get('/semester', 'StudentLearningContractController@semester');
            Route::get('/detail', 'StudentLearningContractController@detail');
        });

        /**
         * STUDENT FORUM REPLACEMENT CLASS
         *
         */
        Route::prefix('replacement-class')->group(function(){
            Route::get('/', 'StudentReplacementClassController@replacementClass');
            Route::get('/header', 'StudentReplacementClassController@header');
            Route::get('/semester', 'StudentReplacementClassController@semester');
            Route::get('/detail', 'StudentReplacementClassController@detail');
        });

        /**
         * STUDENT FORUM SCORE
         *
         */
        Route::prefix('score')->group(function () {
            Route::get('/header', 'StudentForumScoreController@header');
            Route::get('/', 'StudentForumScoreController@scoreList');
        });

        /**
         * STUDENT FORUM STUDY SESSION
         *
         */
        Route::prefix('study-session')->group(function(){
            Route::post('join', 'StudentForumController@join');
            Route::post('hang-up', 'StudentForumController@hangUp');
        });

        /**
         *  STUDENT FORUM STUDENT MEMBER
         *
         */
        Route::get('/student-member', 'StudentClassroomController@studentMember');
    });

    // STUDENT TASK
    Route::get('/task', 'StudentTaskController@task');

    // STUDENT TEST
    Route::get('/test', 'StudentTestController@test');

    // STUDENT ACHIEVEMENT
    Route::prefix('achievement')->group(function() {
        Route::get('/', 'StudentAchievementController@list');
        Route::get('/{id}', 'StudentAchievementController@detail');
    });

    // STUDENT OFFENCE
    Route::prefix('offence')->group(function() {
        Route::get('/', 'StudentOffenceController@list');
        Route::get('/detail', 'StudentOffenceController@detail');
    });

    // STUDENT ANNOUNCEMENT
    Route::prefix('announcement')->group(function() {
        Route::get('/', 'StudentAnnouncementController@list');
    });

    // STUDENT ATTENDANCE
    Route::prefix('attendance')->group(function() {
        Route::get('/detail', 'StudentAttendanceController@detail');
    });

    /**
     * STUDENT LEARNING MATERIALS
     *
     */
    Route::prefix('learning-material')->group(function(){
        Route::get('/grade', 'StudentLearningMaterialController@grade');
        Route::get('/semester', 'StudentLearningMaterialController@semester');
        Route::get('/subjects', 'StudentLearningMaterialController@subjects');
        Route::get('/list', 'StudentLearningMaterialController@list');
        Route::get('/detail', 'StudentLearningMaterialController@detail');
    });
});

/**
 * PARENT
 *
 */
Route::middleware('jwt:parents')->prefix('parent')->group(function(){
    /**
     * PARENT PROFILE
     *
     */
    Route::prefix('profile')->group(function(){
        Route::get('/detail', 'ParentProfileController@detail');
        /**
         * PARENT PROFILE ATTENDANCE
         *
         */
        Route::prefix('attendance')->group(function(){
            Route::get('/', 'ParentProfileController@attendance');
            Route::get('/detail', 'ParentProfileController@attendanceDetail');
            Route::get('/history', 'ParentProfileController@attendanceHistory');
        });

        /**
         * PARENT PROFILE LEARNING PROGRESS
         *
         */
        Route::prefix('learning-progress')->group(function(){
            Route::get('/detail', 'ParentProfileController@learningProgress');
        });
    });

    /**
     * PARENT SCHOOL PAYMENT
     *
     */
    Route::prefix('school-payment')->group(function(){
        Route::get('/list', 'ParentPaymentController@list');
        Route::get('/home', 'ParentPaymentController@home');

        Route::get('/unpaid', 'ParentPaymentController@unpaid');
        Route::get('/pending', 'ParentPaymentController@pending');
        Route::get('/finish', 'ParentPaymentController@finish');

        Route::get('/pay/{id}', 'ParentPaymentController@detailPayment');
        Route::post('/pay/{id}', 'ParentPaymentController@confirmPayment');
    });
});


/**
 * Chatroom
 *
 */
Route::middleware('jwt')->group(function() {
    Route::post('/fcm/store', 'FcmController@store');

    Route::prefix('roomchat')->group(function() {
        Route::get('/classroom-list', 'RoomchatController@classroomList');
        Route::get('/member-list', 'RoomchatController@memberList');

        Route::get('/', 'RoomchatController@list');
        Route::get('/{id}', 'RoomchatController@open');
        Route::post('/', 'RoomchatController@create');
        Route::delete('/{id}', 'RoomchatController@delete');
    });

    Route::prefix('chat')->group(function() {
        Route::post('/', 'ChatController@sendMessage');
        Route::delete('/{id}', 'ChatController@deleteMessage');
    });

    Route::get('/notification', 'NotificationController@all');
});