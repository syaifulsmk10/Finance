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
            'name' => 'CEO',
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
        Position::create(['name' => 'Finance']);
        Position::create(['name' => 'CEO']);
        Position::create(['name' => 'Manager']);
        Position::create(['name' => 'GA']);
        Position::create(['name' => 'Human Resource Staff']);
        Position::create(['name' => 'Frontend Developer']);
        Position::create(['name' => 'Backend Developer']);
        Position::create(['name' => 'Data Enginer']);
        Position::create(['name' => 'ML Enginer']);
        Position::create(['name' => 'Account Staff']);
        Position::create(['name' => 'Macro Staff']);
        Department::create(['name' => 'Human Resource']);
        Department::create(['name' => 'Software']);
        Department::create(['name' => 'Data']);
        Department::create(['name' => 'Account Management']);
        Department::create(['name' => 'Finance']);
        Department::create(['name' => 'General Affair']);
        Department::create(['name' => 'Project Division']);
        Department::create(['name' => 'HR Admin']);
        Department::create(['name' => 'Product Department']);
        User::create([
            'role_id' => 1, 
            'position_id' => 2, 
            'name' => 'Megandi',
            'username' => 'Megandi',
            'email' => 'Megandi@intiva.id',
            'password' => Hash::make('Megandi123'),
            'path' => 'https://intiva.id/static/media/megandi2.9b979decc461a5c218911dc53fdf3fed.svg'
            

        ]);
        User::create([
            'role_id' => 2, 
            'position_id' => 3, 
            'department_id' => 4,
            'name' => 'Elfin Prayoga',
            'username' => 'Elfin Prayoga',
            'email' => 'Elfin@intiva.id',
            'password' => Hash::make('Elfin123'),
            'path' => 'https://intiva.id/static/media/elfin2.68d82bb11bed8858ffe332061a49878e.svg'
            
        ]);
        User::create([
            'role_id' => 2, 
            'position_id' => 3, 
            'department_id' => 2,
            'name' => 'Fathan Satria',
            'username' => 'Fathan Satria',
            'email' => 'Fathan@intiva.id',
            'password' => Hash::make('Fathan123'),
            'path' => 'https://intiva.id/static/media/fathan2.7ae37ba57db1e354cf95aa07bce531fa.svg'
            
        ]);
        User::create([
            'role_id' => 2, 
            'position_id' => 3, 
            'department_id' => 3,
            'name' => 'Sahrul',
            'username' => 'Sahrul',
            'email' => 'Sahrul@intiva.id',
            'password' => Hash::make('Sahrul123'),
            'path' => 'https://intiva.id/static/media/sahrul2.c8850f852a46bb5130899b023aa521ed.svg'
            
        ]);
        User::create([
            'role_id' => 2, 
            'position_id' => 3, 
            'name' => 'Adhytia Ihza M',
            'username' => 'Adhytia Ihza M',
            'email' => 'Adhytia@intiva.id',
            'password' => Hash::make('Adhytia123'),
            'path' => 'https://intiva.id/static/media/adhytia2.4a4360675a335b5dcfa52bbd75ff0715.svg'
            
        ]);

        User::create([
            'role_id' => 3, 
            'position_id' => 4, 
            'department_id' => 6,
            'name' => 'General Affairs',
            'username' => 'General Affairs',
            'email' => 'Generalaffairs@intiva.id',
            'password' => Hash::make('Generalaffairs'),
            
        ]);

        User::create([
            'role_id' => 4, 
            'position_id' => 1, 
            'department_id' => 5,
            'name' => 'Finance',
            'username' => 'Finance',
            'email' => 'Finance@intiva.id',
            'password' => Hash::make('Finance'),
            
        ]);

        User::create([
            'role_id' => 5, 
            'position_id' => 5, 
            'department_id' => 1,
            'name' => 'Nadiyah',
            'username' => 'Nadiyah',
            'email' => 'Nadiyah@intiva.id',
            'password' => Hash::make('nadiyah123'),

        ]);

       
       Staff::create([
        'manager_id' => 3,
        'staff_id' => 8,
        ]);

     

      

      
    }
}
