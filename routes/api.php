<?php

use App\Http\Controllers\AdminApprovalController;
use App\Http\Controllers\DepartmentController;
use App\Http\Controllers\PositionController;
use App\Http\Controllers\BankAccountController;
use App\Http\Controllers\BankController;
use App\Http\Controllers\RoleController;
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



Route::prefix('users')->middleware('auth:sanctum')->group(function () {
    Route::get('/', [UserController::class, 'index']); //done
    Route::post('/', [UserController::class, 'store']); //done
    Route::get('/{id}', [UserController::class, 'show']); //done
    Route::post('/{id}', [UserController::class, 'update']); //done
    Route::delete('/{id}', [UserController::class, 'destroy']); //done
});


Route::prefix('dataApplicant')->middleware('auth:sanctum')->group(function () {
    Route::get('/index', [AdminApprovalController::class, 'index']);
    Route::get('/ammount', [AdminApprovalController::class, 'ammount']); //done
    Route::get('/dashboard', [AdminApprovalController::class, 'dashboard']); //done
    Route::post('/approve/{id}', [AdminApprovalController::class, 'approve']);
    // Route::get('/index', [AdminApprovalController::class, 'index']);
    // Route::get('/detail', [AdminApprovalController::class, 'detail']);
});



Route::prefix('submission')->middleware('auth:sanctum')->group(function () {
    Route::get('/index', [SubmissionController::class, 'index']);
    Route::post('/submissions', [SubmissionController::class, 'store']);
    Route::get('/banks', [BankController::class, 'index']);
    Route::get('/bank-account-detail/{bankId}', [BankAccountController::class, 'getBankAccountDetail']);
    // Route::get('/show/{id}', [SubmissionController::class, 'show']);
    // Route::get('/update/{id}', [SubmissionController::class, 'update']);
    // Route::get('/delete{id}', [SubmissionController::class, 'delete']);
});


