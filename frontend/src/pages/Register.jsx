import { useState } from "react";
import { Link, useNavigate } from "react-router-dom";
import { useAuth } from "../context/AuthContext";

const MIN_PASSWORD = 8;

export default function Register() {
  const { register } = useAuth();
  const navigate = useNavigate();

  const [form, setForm] = useState({
    organization_name: "",
    name: "",
    email: "",
    password: "",
    password_confirmation: "",
    acceptTerms: false,
  });
  const [showPassword, setShowPassword] = useState(false);
  const [submitting, setSubmitting] = useState(false);
  const [errors, setErrors] = useState({});
  const [formError, setFormError] = useState(null);

  const update = (field) => (e) =>
    setForm((f) => ({ ...f, [field]: e.target.value }));

  const validate = () => {
    const next = {};
    if (!form.organization_name.trim()) {
      next.organization_name = "Organization name is required.";
    } else if (form.organization_name.trim().length < 2) {
      next.organization_name = "Organization name is too short.";
    }
    if (!form.name.trim()) next.name = "Your name is required.";
    if (!form.email.trim()) {
      next.email = "Email is required.";
    } else if (!/^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(form.email)) {
      next.email = "Enter a valid email address.";
    }
    if (!form.password) {
      next.password = "Password is required.";
    } else if (form.password.length < MIN_PASSWORD) {
      next.password = `Password must be at least ${MIN_PASSWORD} characters.`;
    }
    if (form.password !== form.password_confirmation) {
      next.password_confirmation = "Passwords do not match.";
    }
    if (!form.acceptTerms) {
      next.acceptTerms = "You must accept the terms to continue.";
    }
    return next;
  };

  const onSubmit = async (e) => {
    e.preventDefault();
    setFormError(null);
    setErrors({});

    const validation = validate();
    if (Object.keys(validation).length > 0) {
      setErrors(validation);
      return;
    }

    setSubmitting(true);
    try {
      const { acceptTerms, ...payload } = form;
      void acceptTerms;
      await register(payload);
      navigate("/dashboard", { replace: true });
    } catch (err) {
      const status = err.response?.status;
      if (status === 422 && err.response.data?.errors) {
        setErrors(err.response.data.errors);
      } else {
        setFormError(
          err.response?.data?.message ||
            "Unable to create your workspace. Please try again."
        );
      }
    } finally {
      setSubmitting(false);
    }
  };

  const passwordStrength = (() => {
    const pw = form.password;
    if (!pw) return { score: 0, label: "", color: "" };
    let score = 0;
    if (pw.length >= MIN_PASSWORD) score++;
    if (/[A-Z]/.test(pw)) score++;
    if (/[0-9]/.test(pw)) score++;
    if (/[^A-Za-z0-9]/.test(pw)) score++;
    const map = [
      { label: "Too short", color: "bg-red-500" },
      { label: "Weak", color: "bg-red-500" },
      { label: "Fair", color: "bg-amber-500" },
      { label: "Good", color: "bg-lime-500" },
      { label: "Strong", color: "bg-emerald-500" },
    ];
    return { score, ...map[score] };
  })();

  const fieldClass = (field) =>
    [
      "mt-1.5 block w-full rounded-lg border px-3 py-2.5 text-slate-900 shadow-sm focus:ring-2 focus:outline-none disabled:bg-slate-50 disabled:text-slate-500",
      errors[field]
        ? "border-red-400 focus:border-red-500 focus:ring-red-500"
        : "border-slate-300 focus:border-indigo-500 focus:ring-indigo-500",
    ].join(" ");

  return (
    <div className="min-h-screen bg-slate-50 flex flex-col justify-center py-12 sm:px-6 lg:px-8">
      <div className="sm:mx-auto sm:w-full sm:max-w-lg">
        <div className="flex items-center justify-center gap-2 mb-6">
          <div className="h-10 w-10 rounded-xl bg-indigo-600 flex items-center justify-center text-white font-bold text-lg">
            P
          </div>
          <span className="text-2xl font-semibold text-slate-900">
            PulseDesk
          </span>
        </div>
        <h2 className="text-center text-2xl font-bold text-slate-900">
          Create your workspace
        </h2>
        <p className="mt-2 text-center text-sm text-slate-600">
          Set up an organization and your admin account in seconds.
        </p>
      </div>

      <div className="mt-8 sm:mx-auto sm:w-full sm:max-w-lg">
        <div className="bg-white py-8 px-6 shadow-sm ring-1 ring-slate-200 rounded-2xl">
          {formError && (
            <div
              role="alert"
              className="mb-4 rounded-lg bg-red-50 border border-red-200 px-4 py-3 text-sm text-red-700"
            >
              {formError}
            </div>
          )}

          <form onSubmit={onSubmit} className="space-y-5" noValidate>
            {/* Organization */}
            <div>
              <label
                htmlFor="organization_name"
                className="block text-sm font-medium text-slate-700"
              >
                Organization name
              </label>
              <input
                id="organization_name"
                name="organization_name"
                type="text"
                required
                value={form.organization_name}
                onChange={update("organization_name")}
                disabled={submitting}
                className={fieldClass("organization_name")}
                placeholder="Acme Support Inc."
              />
              {errors.organization_name && (
                <p className="mt-1 text-xs text-red-600">
                  {errors.organization_name}
                </p>
              )}
            </div>

            {/* Name + Email */}
            <div className="grid grid-cols-1 sm:grid-cols-2 gap-4">
              <div>
                <label
                  htmlFor="name"
                  className="block text-sm font-medium text-slate-700"
                >
                  Your name
                </label>
                <input
                  id="name"
                  name="name"
                  type="text"
                  required
                  value={form.name}
                  onChange={update("name")}
                  disabled={submitting}
                  className={fieldClass("name")}
                  placeholder="Jane Doe"
                />
                {errors.name && (
                  <p className="mt-1 text-xs text-red-600">{errors.name}</p>
                )}
              </div>

              <div>
                <label
                  htmlFor="email"
                  className="block text-sm font-medium text-slate-700"
                >
                  Work email
                </label>
                <input
                  id="email"
                  name="email"
                  type="email"
                  autoComplete="email"
                  required
                  value={form.email}
                  onChange={update("email")}
                  disabled={submitting}
                  className={fieldClass("email")}
                  placeholder="jane@acme.com"
                />
                {errors.email && (
                  <p className="mt-1 text-xs text-red-600">{errors.email}</p>
                )}
              </div>
            </div>

            {/* Password */}
            <div>
              <label
                htmlFor="password"
                className="block text-sm font-medium text-slate-700"
              >
                Password
              </label>
              <div className="mt-1.5 relative">
                <input
                  id="password"
                  name="password"
                  type={showPassword ? "text" : "password"}
                  autoComplete="new-password"
                  required
                  value={form.password}
                  onChange={update("password")}
                  disabled={submitting}
                  className={`${fieldClass("password")} pr-12`}
                  placeholder="At least 8 characters"
                />
                <button
                  type="button"
                  onClick={() => setShowPassword((s) => !s)}
                  className="absolute inset-y-0 right-0 flex items-center pr-3 text-xs font-medium text-slate-500 hover:text-slate-700"
                  tabIndex={-1}
                >
                  {showPassword ? "Hide" : "Show"}
                </button>
              </div>
              {form.password && (
                <div className="mt-2 flex items-center gap-2">
                  <div className="h-1.5 flex-1 rounded-full bg-slate-200 overflow-hidden">
                    <div
                      className={`h-full transition-all ${passwordStrength.color}`}
                      style={{ width: `${(passwordStrength.score / 4) * 100}%` }}
                    />
                  </div>
                  <span className="text-xs text-slate-500 w-14 text-right">
                    {passwordStrength.label}
                  </span>
                </div>
              )}
              {errors.password && (
                <p className="mt-1 text-xs text-red-600">{errors.password}</p>
              )}
            </div>

            {/* Confirm */}
            <div>
              <label
                htmlFor="password_confirmation"
                className="block text-sm font-medium text-slate-700"
              >
                Confirm password
              </label>
              <input
                id="password_confirmation"
                name="password_confirmation"
                type={showPassword ? "text" : "password"}
                autoComplete="new-password"
                required
                value={form.password_confirmation}
                onChange={update("password_confirmation")}
                disabled={submitting}
                className={fieldClass("password_confirmation")}
                placeholder="Re-enter your password"
              />
              {errors.password_confirmation && (
                <p className="mt-1 text-xs text-red-600">
                  {errors.password_confirmation}
                </p>
              )}
            </div>

            {/* Terms */}
            <div>
              <label className="flex items-start gap-2 text-sm text-slate-700">
                <input
                  type="checkbox"
                  checked={form.acceptTerms}
                  onChange={(e) =>
                    setForm((f) => ({ ...f, acceptTerms: e.target.checked }))
                  }
                  disabled={submitting}
                  className="mt-0.5 h-4 w-4 rounded border-slate-300 text-indigo-600 focus:ring-indigo-500"
                />
                <span>
                  I agree to the{" "}
                  <a
                    href="/terms"
                    className="font-medium text-indigo-600 hover:text-indigo-700"
                  >
                    Terms of Service
                  </a>{" "}
                  and{" "}
                  <a
                    href="/privacy"
                    className="font-medium text-indigo-600 hover:text-indigo-700"
                  >
                    Privacy Policy
                  </a>
                  .
                </span>
              </label>
              {errors.acceptTerms && (
                <p className="mt-1 text-xs text-red-600">
                  {errors.acceptTerms}
                </p>
              )}
            </div>

            <button
              type="submit"
              disabled={submitting}
              className="w-full flex justify-center items-center gap-2 rounded-lg bg-indigo-600 px-4 py-2.5 text-sm font-semibold text-white shadow-sm transition hover:bg-indigo-700 focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:ring-offset-2 disabled:cursor-not-allowed disabled:opacity-60"
            >
              {submitting ? (
                <>
                  <svg
                    className="animate-spin h-4 w-4"
                    viewBox="0 0 24 24"
                    fill="none"
                  >
                    <circle
                      className="opacity-25"
                      cx="12"
                      cy="12"
                      r="10"
                      stroke="currentColor"
                      strokeWidth="4"
                    />
                    <path
                      className="opacity-75"
                      fill="currentColor"
                      d="M4 12a8 8 0 018-8v4a4 4 0 00-4 4H4z"
                    />
                  </svg>
                  Creating workspace…
                </>
              ) : (
                "Create workspace"
              )}
            </button>
          </form>

          <p className="mt-6 text-center text-sm text-slate-600">
            Already have a PulseDesk account?{" "}
            <Link
              to="/login"
              className="font-semibold text-indigo-600 hover:text-indigo-700"
            >
              Sign in
            </Link>
          </p>
        </div>
      </div>
    </div>
  );
}
