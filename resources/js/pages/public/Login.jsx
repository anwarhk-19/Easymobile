import { useState } from 'react';
import { Link, Navigate, useNavigate, useSearchParams } from 'react-router-dom';
import Icon from '../../components/icons';
import { Alert, Button, Field, Input } from '../../components/ui';
import { post } from '../../lib/api';
import { homeFor, useAuth } from '../../lib/auth';
import { useAction } from '../../lib/hooks';

const ROLES = [['customer', 'smartphone', 'Shop & repair', 'Buy phones, accessories and book repairs'], ['merchant', 'store', 'Mobile shop', 'List your shop and products'], ['technician', 'wrench', 'Technician', 'Offer repair services']];

export function Login() {
    const [params] = useSearchParams();
    const { user, login } = useAuth();
    const navigate = useNavigate();
    const [step, setStep] = useState('phone');
    const [phone, setPhone] = useState('');
    const [code, setCode] = useState('');
    const [name, setName] = useState('');
    const [role, setRole] = useState(params.get('role') || 'customer');
    const [info, setInfo] = useState(null);

    const send = useAction(() => post('/auth/otp/send', { phone }), { onSuccess: (r) => { setInfo(r); setStep('code'); } });
    const verify = useAction(() => post('/auth/otp/verify', { phone, code, name: name || undefined, role: info?.is_registered ? undefined : role }), {
        onSuccess: (r) => { login(r.token, r.user); const next = params.get('next'); navigate(next && r.user.role === 'customer' ? next : homeFor(r.user.role), { replace: true }); },
    });
    if (user) return <Navigate to={homeFor(user.role)} replace />;
    const err = send.errors.phone?.[0] || verify.errors.code?.[0] || verify.errors.phone?.[0];

    return (
        <div className="mx-auto max-w-md py-6">
            <div className="card p-6 sm:p-8">
                <h1 className="text-2xl font-extrabold text-slate-900">{step === 'phone' ? 'Sign in or create an account' : 'Enter the OTP'}</h1>
                <p className="mt-1 text-sm text-slate-500">{step === 'phone' ? 'We’ll send a one-time password to your mobile number.' : info?.message}</p>
                {err && <div className="mt-4"><Alert tone="red">{err}</Alert></div>}

                {step === 'phone' ? (
                    <form className="mt-6 space-y-4" onSubmit={(e) => { e.preventDefault(); send.run(); }}>
                        <Field label="Mobile number">
                            <div className="flex"><span className="inline-flex items-center rounded-l-xl border border-r-0 border-slate-300 bg-slate-50 px-3 text-slate-600">+91</span>
                                <Input className="rounded-l-none" type="tel" inputMode="numeric" autoComplete="tel-national" maxLength={10} placeholder="98450 12345" value={phone} onChange={(e) => setPhone(e.target.value.replace(/\D/g, ''))} autoFocus required /></div>
                        </Field>
                        <Button className="w-full" size="lg" busy={send.busy} disabled={phone.length !== 10}>Send OTP</Button>
                    </form>
                ) : (
                    <form className="mt-6 space-y-4" onSubmit={(e) => { e.preventDefault(); verify.run(); }}>
                        {info?.debug_code && <Alert tone="blue" icon="message" title="Development mode">Your OTP is <b className="font-mono">{info.debug_code}</b> (SMS is not sent in local development).</Alert>}
                        <Field label="6-digit OTP"><Input inputMode="numeric" autoComplete="one-time-code" maxLength={6} className="text-center font-mono text-2xl tracking-[.5em]" value={code} onChange={(e) => setCode(e.target.value.replace(/\D/g, ''))} autoFocus required /></Field>
                        {!info?.is_registered && (
                            <>
                                <Field label="Your name" error={verify.errors.name}><Input autoComplete="name" value={name} onChange={(e) => setName(e.target.value)} required /></Field>
                                <fieldset>
                                    <legend className="label">I want to</legend>
                                    <div className="space-y-2">{ROLES.map(([r, icon, t, d]) => (
                                        <label key={r} className={`flex cursor-pointer items-center gap-3 rounded-xl border p-3 transition ${role === r ? 'border-brand-600 bg-brand-50' : 'border-slate-200'}`}>
                                            <input type="radio" name="role" value={r} checked={role === r} onChange={() => setRole(r)} className="sr-only" />
                                            <Icon name={icon} className={role === r ? 'text-brand-700' : 'text-slate-400'} />
                                            <span><span className="block text-sm font-semibold text-slate-900">{t}</span><span className="text-xs text-slate-500">{d}</span></span>
                                        </label>
                                    ))}</div>
                                </fieldset>
                            </>
                        )}
                        <Button className="w-full" size="lg" busy={verify.busy} disabled={code.length !== 6}>{info?.is_registered ? 'Verify & sign in' : 'Create account'}</Button>
                        <div className="flex justify-between text-sm"><button type="button" className="font-semibold text-slate-500" onClick={() => setStep('phone')}>Change number</button><button type="button" className="font-semibold text-brand-700" onClick={() => send.run()} disabled={send.busy}>Resend OTP</button></div>
                    </form>
                )}
            </div>
            <p className="mt-4 text-center text-xs text-slate-500">By continuing you agree to the platform terms. Staff? <Link to="/staff" className="font-semibold text-brand-700">Sign in here</Link></p>
        </div>
    );
}

export function StaffLogin() {
    const { user, login } = useAuth();
    const navigate = useNavigate();
    const [params] = useSearchParams();
    const [email, setEmail] = useState('');
    const [password, setPassword] = useState('');
    const act = useAction(() => post('/auth/staff/login', { email, password }), { onSuccess: (r) => { login(r.token, r.user); navigate(params.get('next') || '/admin', { replace: true }); } });
    if (user?.role === 'admin' || user?.role === 'support') return <Navigate to="/admin" replace />;
    return (
        <div className="mx-auto max-w-md py-6">
            <form className="card space-y-4 p-6 sm:p-8" onSubmit={(e) => { e.preventDefault(); act.run(); }}>
                <h1 className="text-2xl font-extrabold text-slate-900">Staff sign in</h1>
                {act.errors.email && <Alert tone="red">{act.errors.email[0]}</Alert>}
                <Field label="Email"><Input type="email" autoComplete="username" value={email} onChange={(e) => setEmail(e.target.value)} required /></Field>
                <Field label="Password"><Input type="password" autoComplete="current-password" value={password} onChange={(e) => setPassword(e.target.value)} required /></Field>
                <Button className="w-full" size="lg" busy={act.busy}>Sign in</Button>
            </form>
        </div>
    );
}
