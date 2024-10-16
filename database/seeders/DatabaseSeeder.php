<?php

namespace Database\Seeders;

// use Illuminate\Database\Console\Seeds\WithoutModelEvents;

use App\Models\AdminApproval;
use App\Models\Bank;
use App\Models\BankAccount;
use App\Models\Department;
use App\Models\Position;
use App\Models\Role;
use App\Models\Staff;
use App\Models\Submission;
use App\Models\SubmissionItem;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class DatabaseSeeder extends Seeder
{
    /**
     * Seed the application's database.
     */
    public function run(): void
    {
        // \App\Models\User::factory(10)->create();

        // \App\Models\User::factory()->create([
        //     'name' => 'Test User',
        //     'email' => 'test@example.com',
        // ]);


        Role::create([
            'name' => '	CEO',
            'level' => 3
        ]);
        Role::create([
            'name' => 'Manager',
            'level' => 2
        ]);
        Role::create([
            'name' => 'GA', 
            'level' => 1
        ]);
        Role::create([
            'name' => 'Finance', 
            'level' => 4
        ]);
        Role::create([
            'name' => 'Employee'
        ]);
        Position::create(['name' => 'CEO']);
        Position::create(['name' => 'Manager']);
        Position::create(['name' => 'Staff']);
        Position::create(['name' => 'Finance Officer']);
        Position::create(['name' => 'Programmer']);
        Position::create(['name' => 'IT Support']);
        Department::create(['name' => 'IT Software']);
        Department::create(['name' => 'Management']);
        Department::create(['name' => 'Data']);
        Department::create(['name' => 'Marchine leraning']);
        User::create([
            'role_id' => 1, 
            'position_id' => 1, 
            'name' => ' Pak Megandi',
            'username' => 'Pak Megandi',
            'email' => 'Intiva@gmail.com',
            'password' => Hash::make('aselole123'),
            

        ]);
        User::create([
            'role_id' => 2, 
            'position_id' => 2, 
            'department_id' => 1,
            'name' => ' Pak Elfin',
            'username' => 'Pak Elfin',
            'email' => 'Intiva1@gmail.com',
            'password' => Hash::make('aselole123'),
            
        ]);
        User::create([
            'role_id' => 2, 
            'position_id' => 2, 
            'department_id' => 2,
            'name' => ' Pak Fatan',
            'username' => 'Pak Fatan',
            'email' => 'Intiva2@gmail.com',
            'password' => Hash::make('aselole123'),
            
        ]);
        User::create([
            'role_id' => 2, 
            'position_id' => 2, 
            'department_id' => 3,
            'name' => ' Pak Sahrul',
            'username' => 'Pak Sahrul',
            'email' => 'Intiva3@gmail.com',
            'password' => Hash::make('aselole123'),
            
        ]);
        User::create([
            'role_id' => 2, 
            'position_id' => 2, 
            'department_id' => 4,
            'name' => ' Pak Adhyit',
            'username' => 'Pak Adhyit',
            'email' => 'Intiva4@gmail.com',
            'password' => Hash::make('aselole123'),
            
        ]);

        User::create([
            'role_id' => 3, 
            'position_id' => 3, 
            'name' => ' Pak Edwin',
            'username' => 'Pak Edwin',
            'email' => 'Intiva5@gmail.com',
            'password' => Hash::make('aselole123'),
            
        ]);

        User::create([
            'role_id' => 4, 
            'position_id' => 4, 
            'name' => 'Mbak Ismi',
            'username' => 'Mbak Ismi',
            'email' => 'Intiva6@gmail.com',
            'password' => Hash::make('aselole123'),
            
        ]);

        User::create([
            'role_id' => 5, 
            'position_id' => 5, 
            'department_id' => 1,
            'name' => 'Bang Piko',
            'username' => 'Bang Piko',
            'email' => 'Intiva7@gmail.com',
            'password' => Hash::make('aselole123'),

        ]);

        User::create([
            'role_id' => 5, 
            'position_id' => 6, 
            'department_id' => 2,
            'name' => 'Bang Adam',
            'username' => 'Bang Adam',
            'email' => 'Intiva8@gmail.com',
            'password' => Hash::make('aselole123'),

        ]);
        Bank::create(['name' => 'BCA']);
        Bank::create(['name' => 'Mandiri']);
        Bank::create(['name' => 'CIMB Niaga']);
        Bank::create(['name' => 'BRI']);
        Bank::create(['name' => 'DKI']);
        Bank::create(['name' => 'DKI']);
        BankAccount::create([
            'user_id' => 2,
            'bank_id' => 1,
            'account_name' => 'Elfin',
            'account_number' => '1234567890'
        ]);
        BankAccount::create([
            'user_id' => 2,
            'bank_id' => 2,
            'account_name' => 'Elfin',
            'account_number' => '1234567890'
        ]);
        BankAccount::create([
            'user_id' => 2,
            'bank_id' => 3,
            'account_name' => 'Elfin',
            'account_number' => '1234567890'
        ]);
        BankAccount::create([
            'user_id' => 8,
            'bank_id' => 4,
            'account_name' => 'Piko',
            'account_number' => '1234567890'
        ]);
        BankAccount::create([
            'user_id' => 8,
            'bank_id' => 1,
            'account_name' => 'Piko',
            'account_number' => '1234567890'
        ]);

        BankAccount::create([
            'user_id' => 9,
            'bank_id' => 1,
            'account_name' => 'Adam',
            'account_number' => '1234567890'
        ]);
        BankAccount::create([
            'user_id' => 9,
            'bank_id' => 2,
            'account_name' => 'Adam',
            'account_number' => '1234567890'
        ]);

       Staff::create([
            'manager_id' => 2,
            'staff_id' => 9,
       ]);
       Staff::create([
        'manager_id' => 3,
        'staff_id' => 8,
        ]);

        Submission::create([
            'user_id'  => 8,
            'type' => 'Reimburesent',
            'purpose' => 'agskagskags',
            'submission_date' => Carbon::now(),
            'due_date' => Carbon::now(),
            'description' => 'kahjdgagdlj',
            'bank_account_id' => 4,
            'finish_status' => 'process',
            'amount' => 200000,
        ]);

        Submission::create([
            'user_id'  => 9,
            'type' => 'Reimburesent',
            'purpose' => 'agskagskags',
            'submission_date' => Carbon::now(),
            'due_date' => Carbon::now(),
            'description' => 'kahjdgagdlj',
            'bank_account_id' => 7,
            'finish_status' => 'process',
            'amount' => 500000,
        ]);

        SubmissionItem::create([
            'submission_id' => 1,
            'quantity' => 5,
            'price' => 200000,
        ]);

        SubmissionItem::create([
            'submission_id' => 2,
            'quantity' => 2,
            'price' => 500000,
        ]);

        AdminApproval::create([
            'user_id' => 6,
            'submission_id' => 2,
            'status' => 'approved',
            'notes' => 'diterima',
            'approved_at' => Carbon::now()
        ]);

        AdminApproval::create([
            'user_id' => 2,
            'submission_id' => 2,
            'status' => 'approved',
            'notes' => 'diterima',
            'approved_at' => Carbon::now()
        ]);

        AdminApproval::create([
            'user_id' => 6,
            'submission_id' => 1,
            'status' => 'pending',
            'notes' => 'diterima',
            'approved_at' => Carbon::now()
        ]);


    }
}
