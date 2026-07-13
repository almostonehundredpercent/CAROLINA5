<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Sign In</title>

    <style>
        *{
            margin:0;
            padding:0;
            box-sizing:border-box;
        }

        body{
            font-family: "Segoe UI", Tahoma, Geneva, Verdana, sans-serif;
            background:#f7f7f7;
            display:flex;
            justify-content:center;
            align-items:center;
            min-height:100vh;
        }

        .login-card{
            width:400px;
            max-width:90%;
            background:#fff;
            border-radius:16px;
            padding:40px;
            box-shadow:0 15px 40px rgba(0,0,0,.08);
        }

        h1{
            color:#222;
            text-align:center;
            margin-bottom:8px;
            font-size:30px;
        }

        .subtitle{
            text-align:center;
            color:#777;
            margin-bottom:35px;
        }

        .form-group{
            margin-bottom:18px;
        }

        label{
            display:block;
            margin-bottom:8px;
            font-weight:600;
            color:#444;
        }

        input{
            width:100%;
            padding:14px;
            border:1px solid #ddd;
            border-radius:10px;
            font-size:15px;
            transition:.2s;
            outline:none;
        }

        input:focus{
            border-color:#fb9e2d;
            box-shadow:0 0 0 3px rgba(251,158,45,.18);
        }

        .btn{
            width:100%;
            padding:14px;
            margin-top:8px;
            border:none;
            border-radius:10px;
            background:#fb9e2d;
            color:white;
            font-size:16px;
            font-weight:600;
            cursor:pointer;
            transition:.2s;
        }

        .btn:hover{
            background:#ea8d18;
        }

        .error{
            background:#ffe5e5;
            color:#b00020;
            padding:12px;
            border-radius:8px;
            margin-bottom:18px;
            font-size:14px;
        }

        .links{
            margin-top:25px;
            text-align:center;
            color:#666;
        }

        .links a{
            color:#fb9e2d;
            text-decoration:none;
            font-weight:600;
        }

        .links a:hover{
            text-decoration:underline;
        }

        .back{
            display:block;
            text-align:center;
            margin-top:18px;
            color:#888;
            text-decoration:none;
            font-size:14px;
        }

        .back:hover{
            color:#fb9e2d;
        }
    </style>
</head>
<body>

<div class="login-card">

    <h1>Welcome Back</h1>
    <p class="subtitle">Sign in to your Carolina account</p>

    @if ($errors->any())
        <div class="error">
            {{ $errors->first() }}
        </div>
    @endif

    <form action="{{ route('login') }}" method="POST">
        @csrf

        <div class="form-group">
            <label>Email</label>
            <input
                type="email"
                name="email"
                value="{{ old('email') }}"
                placeholder="Enter your email"
                required
            >
        </div>

        <div class="form-group">
            <label>Password</label>
            <input
                type="password"
                name="password"
                placeholder="Enter your password"
                required
            >
        </div>

        <button class="btn" type="submit">
            Sign In
        </button>
    </form>

    <div class="links">
        Don't have an account?
        <a href="{{ route('register') }}">Create one</a>
    </div>

    <a href="{{ route('login') }}" class="back">
        ← Back
    </a>

</div>

</body>
</html>