# Free external testing deployment (Render)

This project is ready for free test deployment on Render. The included `render.yaml` creates a Laravel web service and a PostgreSQL database named `carolina5`.

> The free plan is for testing only: the web service sleeps after inactivity and the free database expires after 30 days. Keep an export of test data if you need it later.

## 1. Put the project on GitHub

Create a **private** GitHub repository and upload this project's files. Do not upload `.env`, SQL exports, real guest details, or payment information. The `.gitignore` file already excludes those items.

## 2. Create the Render Blueprint

1. Sign in to Render with GitHub.
2. Choose **New** then **Blueprint**.
3. Select your `carolina5` repository. Render will find `render.yaml` and show the web service plus the `carolina5` PostgreSQL database.
4. Choose **Apply**.

Render will request the following private values. Use a strong, new password and do not use your XAMPP password:

| Variable | Value |
| --- | --- |
| `APP_KEY` | Run `php artisan key:generate --show` locally and paste the result. |
| `APP_URL` | Add the Render public URL after the first deployment. |
| `ADMIN_NAME` | The name you want shown for the administrator. |
| `ADMIN_EMAIL` | The email address used to sign in as administrator. |
| `ADMIN_PASSWORD` | A strong, unique administrator password. |

The initial deployment creates the room catalogue and this administrator. It does **not** create publicly known demo accounts.

## 3. Finish the first deployment

1. When Render finishes, open the web service and copy its public URL.
2. Add that URL as `APP_URL` in the service's Environment settings (including `https://`).
3. Change `RUN_INITIAL_SEED` to `false` and redeploy once. This prevents the initial setup task from running on later deployments.
4. Sign in at `/login` using the administrator email and password you chose.

## 4. Test the public site

Check registration, sign-in, guest checkout, booking lookup, receipt display, and the admin dashboard. The online data is separate from your XAMPP database.

## Email note

For this test deployment, confirmation emails are written to application logs. Before using the site with real guests, configure a real transactional email provider through Render environment variables and verify that messages are delivered.

## Moving past free testing

Before the free database expires, export your data and move to a paid database or hosting plan. Never use the free deployment for real payment details or production guest records.
