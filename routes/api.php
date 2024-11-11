<?php

use App\Http\Controllers\AdminApprovalController;
use App\Http\Controllers\DepartmentController;
use App\Http\Controllers\PositionController;
use App\Http\Controllers\BankAccountController;
use App\Http\Controllers\BankController;
use App\Http\Controllers\RoleController;
use App\Http\Controllers\StaffController;
use App\Http\Controllers\SubmissionController;
use App\Http\Controllers\UserController;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| API Routes
|--------------------------------------------------------------------------
|
| Here is where you can register API routes for your application. These
| routes are loaded by the RouteServiceProvider and all of them will
| be assigned to the "api" middleware group. Make something great!
|
*/



Route::middleware('auth:sanctum')->get('/user', function (Request $request) {
    return $request->user();
});


Route::post('/register', [UserController::class, 'registerUser']);
Route::post("/login", [UserController::class, 'postLogin'])->name("login"); //done


Route::prefix('roles')->middleware('auth:sanctum')->group(function () {
    Route::get('/', [RoleController::class, 'index']); //done
    Route::post('/', [RoleController::class, 'store']); //done
    Route::get('/{id}', [RoleController::class, 'show']); //done
    Route::post('/{id}', [RoleController::class, 'update']); //done
    Route::delete('/{id}', [RoleController::class, 'destroy']); //done
});

Route::prefix('positions')->middleware('auth:sanctum')->group(function () {
    Route::get('/', [PositionController::class, 'index']); //done
    Route::post('/', [PositionController::class, 'store']); //done
    Route::get('/{id}', [PositionController::class, 'show']); //done
    Route::post('/{id}', [PositionController::class, 'update']); //done
    Route::delete('/{id}', [PositionController::class, 'destroy']); //done
});


Route::prefix('department')->middleware('auth:sanctum')->group(function () {
    Route::get('/', [DepartmentController::class, 'index']); //done
    Route::post('/', [DepartmentController::class, 'store']); //done
    Route::get('/{id}', [DepartmentController::class, 'show']); //done
    Route::post('/{id}', [DepartmentController::class, 'update']); //done
    Route::delete('/{id}', [DepartmentController::class, 'destroy']); //done
});

Route::prefix('bank')->middleware('auth:sanctum')->group(function () {
    Route::get('/bank', [BankController::class, 'bank']); //done
    Route::post('/bank', [BankController::class, 'store']); //done
    Route::get('bank/{id}', [BankController::class, 'show']); //done
    Route::post('bank/{id}', [BankController::class, 'update']); //done
    Route::delete('bank/{id}', [BankController::class, 'destroy']); //done

});



Route::prefix('users')->middleware('auth:sanctum')->group(function () {
    Route::get('/', [UserController::class, 'index']); //done
    Route::post('/', [UserController::class, 'store']); //done
    Route::get('/{id}', [UserController::class, 'show']); //done
    Route::post('/{id}', [UserController::class, 'update']); //done
    Route::delete('/{id}', [UserController::class, 'destroy']); //done
});


Route::prefix('dataApplicant')->middleware('auth:sanctum')->group(function () {
    Route::get('/index', [AdminApprovalController::class, 'index']); //done
    Route::get('/ammount', [AdminApprovalController::class, 'ammount']); //done
    Route::get('/dashboard', [AdminApprovalController::class, 'dashboard']); //done
    Route::post('/approve/{id}', [AdminApprovalController::class, 'approve']); //done
    Route::post('/denied/{id}', [AdminApprovalController::class, 'denied']); //done
    Route::get('/detail/{id}', [AdminApprovalController::class, 'detail']); //done
});



Route::prefix('submission')->middleware('auth:sanctum')->group(function () {
    Route::get('/index', [SubmissionController::class, 'index']); //done
    Route::post('/update/{id}', [SubmissionController::class, 'update']); //done
    Route::get('/detail/{id}', [SubmissionController::class, 'detail']);  //done
    Route::post('/submissions', [SubmissionController::class, 'store']); //done
    Route::get('/banks', [BankController::class, 'index']); //done
    Route::get('/bank-account-detail/{bankId}', [BankAccountController::class, 'getBankAccountDetail']); //done
    Route::post('/update/profiles/{id}', [UserController::class, 'updateprofiles']); //pending
    Route::post('/create/profiles/{id}', [UserController::class, 'updateprofiles']); //pending
});


Route::prefix('manager')->middleware('auth:sanctum')->group(function () {
    Route::get('/manager', [StaffController::class, 'manager']); //done
});














