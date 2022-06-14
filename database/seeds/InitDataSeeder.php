<?php

use App\Models\Classroom;
use App\Models\ClassroomStudent;
use App\Models\LearningContract;
use App\Models\LearningContractInformation;
use App\Models\Schedule;
use App\Models\SchoolClassroom;
use Illuminate\Database\Seeder;

class InitDataSeeder extends Seeder
{
    /**
     * Run the database seeds.
     *
     * @return void
     */
    public function run()
    {
        // Classroom
        $classroom = Classroom::insert([
            'teacher_id' => 2,
            'code' => "c10aipa",
            'name' => "10 A IPA",
            'grade' => 10,
            'classroom_type' => "regular",
            'major_id' => 2,
            'capacity' => 40
        ]);

        // School Classroom
        SchoolClassroom::create([
            'school_id' => 3,
            'classroom_id' => 1
        ]);

        // Classroom Student
        ClassroomStudent::insert([
            [
                'is_leader' => TRUE,
                'classroom_id' => 1,
                'student_id' => 1,
                'is_active' => TRUE,
                'school_year' => "2021/2022"
            ],
            [
                'is_leader' => FALSE,
                'classroom_id' => 1,
                'student_id' => 2,
                'is_active' => TRUE,
                'school_year' => "2021/2022"
            ]
        ]);

        // Learning Contract
        $LC = LearningContract::create([
            'subject_id' => 1,
            'teacher_id' => 1,
            'grade' => 10,
            'semester' => 1,
            'school_year' => "2021/2022",
            'is_used' => true
        ]);

        // Learning Contract Information
        LearningContractInformation::insert([
            [
                'learning_contract_id' => 1,
                'week' => 1,
                'session' => 1,
                'hour' => 2,
                'basic_competency' => "Understanding about national ideology and its threats",
                'main_topic' => "Indonesian Ideology: Pancasila",
                'sub_topic' => "Introduction: Pancasila",
                'category' => "lesson"
            ],
            [
                'learning_contract_id' => 1,
                'week' => 1,
                'session' => 2,
                'hour' => 2,
                'basic_competency' => "Understanding about national ideology and its threats",
                'main_topic' => "Indonesian Ideology: Pancasila",
                'sub_topic' => "The Born of Pancasila",
                'category' => "lesson"
            ],
            [
                'learning_contract_id' => 1,
                'week' => 1,
                'session' => 1,
                'hour' => 2,
                'basic_competency' => "Understanding about national ideology and its threats",
                'main_topic' => "Pancasila's Threats",
                'sub_topic' => "Many kind of threats",
                'category' => "lesson"
            ],
            [
                'learning_contract_id' => 1,
                'week' => 1,
                'session' => 1,
                'hour' => 2,
                'basic_competency' => "Understanding about national ideology and its threats",
                'main_topic' => "Pancasila's Threats",
                'sub_topic' => "Stand with Pancasila",
                'category' => "lesson"
            ]
        ]);

        // Schedules
        Schedule::insert([
            [
                'subject_id' => 1,
                'teacher_id' => 1,
                'classroom_id' => 1,
                'learning_contract_information_id' => 1,
                'main_topic' => "Indonesian Ideology: Pancasila",
                'sub_topic' => "Introduction: Pancasila",
                'date' => "2021-05-17",
                'start_at' => "09:00:00",
                'end_at' => "10:30:00",
                'schedule_type' => "semester",
                'school_year' => "2021/2022",
                'semester' => 1
            ],
            [
                'subject_id' => 1,
                'teacher_id' => 1,
                'classroom_id' => 1,
                'learning_contract_information_id' => 2,
                'main_topic' => "Indonesian Ideology: Pancasila",
                'sub_topic' => "The Born of Pancasila",
                'date' => "2021-05-19",
                'start_at' => "13:30:00",
                'end_at' => "15:00:00",
                'schedule_type' => "semester",
                'school_year' => "2021/2022",
                'semester' => 1
            ],
            [
                'subject_id' => 1,
                'teacher_id' => 1,
                'classroom_id' => 1,
                'learning_contract_information_id' => 3,
                'main_topic' => "Pancasila's Threats",
                'sub_topic' => "Many kind of threats",
                'date' => "2021-05-24",
                'start_at' => "09:00:00",
                'end_at' => "10:30:00",
                'schedule_type' => "semester",
                'school_year' => "2021/2022",
                'semester' => 1
            ],
            [
                'subject_id' => 1,
                'teacher_id' => 1,
                'classroom_id' => 1,
                'learning_contract_information_id' => 4,
                'main_topic' => "Pancasila's Threats",
                'sub_topic' => "Stand with Pancasila",
                'date' => "2021-05-26",
                'start_at' => "13:30:00",
                'end_at' => "15:00:00",
                'schedule_type' => "semester",
                'school_year' => "2021/2022",
                'semester' => 1
            ],
        ]);
    }
}
