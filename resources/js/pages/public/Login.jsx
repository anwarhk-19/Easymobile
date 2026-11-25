import { useEffect, useRef, useState } from 'react';
import { Link, Navigate, useNavigate, useSearchParams } from 'react-router-dom';
import Icon from '../../components/icons';
import OtpInput from '../../components/OtpInput';
import { Alert, Button, Field, Input } from '../../components/ui';
import { post } from '../../lib/api';
import { homeFor, useAuth } from '../../lib/auth';
import { useAction } from '../../lib/hooks';

const ROLES = [
    ['customer', 'smartphone', 'Customer', 'Buy & repair'],
    ['merchant', 'store', 'Shop owner', 'Sell products'],
    ['technician', 'wrench', 'Technician', 'Offer repairs'],
];
const RESEND_SECONDS = 30;

const pretty = (p) => `+91 ${p.slice(0, 5)} ${p.slice(5)}`;

function AuthShell({ step, total, title, subtitle, onBack, children }) {
    return (
        <div className="mx-auto max-w-md py-4 sm:py-8">
            <div className="card overflow-hidden">
                <div className="bg-gradient-to-br from-brand-600 to-brand-800 px-6 pb-6 pt-5 text-white">
                    <div className="flex items-center justify-between">
                        {onBack ? <button type="button" onClick={onBack} className="-ml-2 rounded-lg p-2 text-white/90 hover:bg-white/10" aria-label="Back"><Icon name="back" /></button> : <span className="flex h-9 w-9 items-center justify-center rounded-xl bg-white/15"><Icon name="smartphone" className="h-5 w-5" strokeWidth={2} /></span>}
                        {total > 1 && (
                            <ol className="flex gap-1.5" aria-label={`Step ${step} of ${total}`}>
                                {Array.from({ length: total }, (_, i) => <li key={i} className={`h-1.5 rounded-full transition-all ${i < step ? 'w-6 bg-white' : 'w-3 bg-white/35'}`} />)}
                            </ol>
                        )}
                    </div>
                    <h1 className="mt-4 text-2xl font-extrabold tracking-tight">{title}</h1>
                    {subtitle && <p className="mt-1 text-sm text-brand-50/90">{subtitle}</p>}
                </div>
                <div className="p-6">{children}</div>
            </div>
        </div>
    );
}

export function Login() {
    const [params] = useSearchParams();
    const { user, login } = useAuth();
    const navigate = useNavigate();
    const [step, setStep] = useState('phone'); // phone → code → profile (new users only)
    const [phone, setPhone] = useState('');
    const [code, setCode] = useState('');
    const [name, setName] = useState('');
    const [role, setRole] = useState(['customer', 'merchant', 'technician'].includes(params.get('role')) ? params.get('role') : 'customer');
    const [info, setInfo] = useState(null);
    const [wait, setWait] = useState(0);
    const nameRef = useRef();

    useEffect(() => {
        if (wait <= 0) return undefined;
        const t = setTimeout(() => setWait((w) => w - 1), 1000);
        return () => clearTimeout(t);
    }, [wait]);
    useEffect(() => { if (step === 'profile') nameRef.current?.focus(); }, [step]);

    const send = useAction(() => post('/auth/otp/send', { phone }), { onSuccess: (r) => { setInfo(r); setCode(''); setStep('code'); setWait(RESEND_SECONDS); } });
    const verify = useAction((c = code) => post('/auth/otp/verify', { phone, code: c, name: name || undefined, role: info?.is_registered ? undefined : role }), {
        onSuccess: (r) => { login(r.token, r.user); const next = params.get('next'); navigate(next && r.user.role === 'customer' ? next : homeFor(r.user.role), { replace: true }); },
        onError: (e) => { if (e.errors?.code) { setCode(''); setStep('code'); } },
    });
    if (user) return <Navigate to={homeFor(user.role)} replace />;

    const isNew = info && !info.is_registered;
    const codeDone = (c) => (isNew ? setStep('profile') : verify.run(c));
    const codeErr = verify.errors.code?.[0];

    if (step === 'phone') {
        return (
            <AuthShell step={1} total={isNew === false ? 2 : 3} title="Welcome" subtitle="Sign in or create an account with your mobile number.">
                <form className="space-y-5" onSubmit={(e) => { e.preventDefault(); send.run(); }}>
                    <Field label="Mobile number" error={send.errors.phone}>
                        <div className="flex overflow-hidden rounded-xl border border-slate-300 bg-white focus-within:border-brand-500 focus-within:ring-4 focus-within:ring-brand-100">
                            <span className="inline-flex items-center border-r border-slate-200 bg-slate-50 px-3.5 text-[17px] font-semibold text-slate-600">+91</span>
                            <input className="w-full min-w-0 px-3.5 py-3 text-[17px] font-semibold tracking-wide text-slate-900 placeholder:font-normal placeholder:text-slate-400 focus:outline-none"
                                type="tel" inputMode="numeric" autoComplete="tel-national" maxLength={10} placeholder="10-digit number" value={phone} onChange={(e) => setPhone(e.target.value.replace(/\D/g, ''))} autoFocus required aria-label="Mobile number" />
                        </div>
                    </Field>
                    <Button className="w-full" size="lg" busy={send.busy} disabled={phone.length !== 10}>Continue<Icon name="chevron" className="h-4 w-4" /></Button>
                    <p className="text-center text-xs text-slate-500">New here? You’ll choose whether you’re a customer, shop owner or technician next.</p>
                </form>
                <p className="mt-6 border-t border-slate-100 pt-4 text-center text-xs text-slate-500">By continuing you agree to the platform terms. Staff? <Link to="/staff" className="font-semibold text-brand-700">Sign in here</Link></p>
            </AuthShell>
        );
    }

    if (step === 'code') {
        return (
            <AuthShell step={2} total={isNew ? 3 : 2} title="Enter the code" subtitle={<>Sent to <b className="text-white">{pretty(phone)}</b> · <button type="button" className="font-semibold text-white underline underline-offset-2" onClick={() => setStep('phone')}>Edit</button></>} onBack={() => setStep('phone')}>
                <div className="space-y-5">
                    {info?.test_mode && <Alert tone="amber" icon="shield" title="Testing mode">No SMS is sent. Enter the test code from your administrator.</Alert>}
                    {info?.debug_code && <Alert tone="blue" icon="message" title="Development mode">Your code is <b className="font-mono">{info.debug_code}</b>.</Alert>}
                    <OtpInput value={code} onChange={setCode} onComplete={codeDone} invalid={!!codeErr} disabled={verify.busy} />
                    {codeErr && <p className="text-center text-sm font-medium text-red-600" role="alert">{codeErr}</p>}
                    <Button className="w-full" size="lg" busy={verify.busy} disabled={code.length !== 6} onClick={() => codeDone(code)}>{isNew ? 'Continue' : 'Sign in'}</Button>
                    <p className="text-center text-sm text-slate-500">
                        {wait > 0 ? <>Resend code in <b className="tabular-nums text-slate-700">0:{String(wait).padStart(2, '0')}</b></>
                            : <>Didn’t get it? <button type="button" className="font-semibold text-brand-700" onClick={() => send.run()} disabled={send.busy}>Resend code</button></>}
                    </p>
                    {send.errors.phone && <p className="text-center text-sm text-red-600">{send.errors.phone[0]}</p>}
                </div>
            </AuthShell>
        );
    }

    return (
        <AuthShell step={3} total={3} title="Create your account" subtitle="Just two quick details." onBack={() => setStep('code')}>
            <form className="space-y-5" onSubmit={(e) => { e.preventDefault(); verify.run(); }}>
                <Field label="Your name" error={verify.errors.name}><Input ref={nameRef} autoComplete="name" value={name} onChange={(e) => setName(e.target.value)} placeholder="Full name" required /></Field>
                <fieldset>
                    <legend className="label">I am a</legend>
                    <div className="grid grid-cols-3 gap-2">{ROLES.map(([r, icon, t, d]) => (
                        <label key={r} className={`flex cursor-pointer flex-col items-center gap-1.5 rounded-2xl border-2 p-3 text-center transition ${role === r ? 'border-brand-600 bg-brand-50' : 'border-slate-200 hover:border-slate-300'}`}>
                            <input type="radio" name="role" value={r} checked={role === r} onChange={() => setRole(r)} className="sr-only" />
                            <span className={`rounded-xl p-2 ${role === r ? 'bg-brand-600 text-white' : 'bg-slate-100 text-slate-500'}`}><Icon name={icon} className="h-5 w-5" /></span>
                            <span className="text-sm font-bold leading-tight text-slate-900">{t}</span>
                            <span className="text-[11px] leading-tight text-slate-500">{d}</span>
                        </label>
                    ))}</div>
                </fieldset>
                {verify.errors.code && <Alert tone="red">{verify.errors.code[0]}</Alert>}
                <Button className="w-full" size="lg" busy={verify.busy} disabled={!name.trim()}>Create account</Button>
            </form>
        </AuthShell>
    );
}

export function StaffLogin() {
    const { user, login } = useAuth();
    const navigate = useNavigate();
    const [params] = useSearchParams();
    const [email, setEmail] = useState('');
    const [password, setPassword] = useState('');
    const act = useAction(() => post('/auth/staff/login', { email, password }), { onSuccess: (r) => { login(r.token, r.user); navigate(params.get('next') || '/admin', { replace: true }); } });
    const [show, setShow] = useState(false);
    if (user?.role === 'admin' || user?.role === 'support') return <Navigate to="/admin" replace />;
    return (
        <AuthShell step={1} total={1} title="Staff sign in" subtitle="For the admin and support team.">
            <form className="space-y-4" onSubmit={(e) => { e.preventDefault(); act.run(); }}>
                {act.errors.email && <Alert tone="red">{act.errors.email[0]}</Alert>}
                <Field label="Email"><Input type="email" autoComplete="username" value={email} onChange={(e) => setEmail(e.target.value)} autoFocus required /></Field>
                <Field label="Password">
                    <div className="relative">
                        <Input type={show ? 'text' : 'password'} autoComplete="current-password" className="pr-16" value={password} onChange={(e) => setPassword(e.target.value)} required />
                        <button type="button" onClick={() => setShow((s) => !s)} className="absolute right-2 top-1/2 -translate-y-1/2 rounded-lg px-2 py-1 text-xs font-semibold text-slate-500 hover:bg-slate-100">{show ? 'Hide' : 'Show'}</button>
                    </div>
                </Field>
                <Button className="w-full" size="lg" busy={act.busy}>Sign in</Button>
                <p className="text-center text-xs text-slate-500">Customer, shop or technician? <Link to="/login" className="font-semibold text-brand-700">Sign in with your mobile</Link></p>
            </form>
        </AuthShell>
    );
}
