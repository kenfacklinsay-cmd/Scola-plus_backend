<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        $duplicate = DB::table('assignments')
            ->select('class_id', 'subject_id')
            ->groupBy('class_id', 'subject_id')
            ->havingRaw('COUNT(*) > 1')
            ->first();

        if ($duplicate !== null) {
            throw new RuntimeException(
                'Plusieurs enseignants sont affectés à la même matière et à la même classe. '
                .'Corrigez ces affectations avant de relancer cette migration.',
            );
        }

        Schema::table('assignments', function (Blueprint $table) {
            $table->dropUnique('assignments_teacher_id_class_id_subject_id_unique');
            $table->unique(['class_id', 'subject_id'], 'assignments_class_subject_unique');
        });
    }

    public function down(): void
    {
        Schema::table('assignments', function (Blueprint $table) {
            $table->dropUnique('assignments_class_subject_unique');
            $table->unique(['teacher_id', 'class_id', 'subject_id']);
        });
    }
};
