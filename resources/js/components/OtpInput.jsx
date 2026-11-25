import { useEffect, useRef } from 'react';

/**
 * Six one-digit boxes for an OTP: typing moves forward, Backspace moves back, pasting or SMS autofill
 * fills every box. Calls onComplete(code) when all digits are entered.
 */
export default function OtpInput({ value, onChange, onComplete, length = 6, invalid, autoFocus = true, disabled }) {
    const refs = useRef([]);
    const digits = Array.from({ length }, (_, i) => value[i] || '');

    useEffect(() => { if (autoFocus) refs.current[0]?.focus(); }, [autoFocus]);

    const setAt = (i, d) => {
        const next = (value.slice(0, i) + d + value.slice(i + 1)).replace(/\D/g, '').slice(0, length);
        onChange(next);
        if (next.length === length) onComplete?.(next);
    };
    const fill = (text, from = 0) => {
        const clean = text.replace(/\D/g, '').slice(0, length - from);
        if (!clean) return;
        const next = (value.slice(0, from) + clean).slice(0, length);
        onChange(next);
        refs.current[Math.min(next.length, length - 1)]?.focus();
        if (next.length === length) onComplete?.(next);
    };

    return (
        <div className="flex justify-between gap-2" role="group" aria-label="One-time password">
            {digits.map((d, i) => (
                <input key={i} ref={(el) => { refs.current[i] = el; }} value={d} disabled={disabled}
                    inputMode="numeric" pattern="[0-9]*" autoComplete={i === 0 ? 'one-time-code' : 'off'} maxLength={i === 0 ? length : 1}
                    aria-label={`Digit ${i + 1}`}
                    className={`h-14 w-full min-w-0 rounded-xl border-2 bg-white text-center font-mono text-2xl font-bold text-slate-900 transition focus:outline-none focus:ring-4 ${invalid ? 'border-red-400 focus:border-red-500 focus:ring-red-100' : d ? 'border-brand-500 focus:ring-brand-100' : 'border-slate-200 focus:border-brand-500 focus:ring-brand-100'}`}
                    onChange={(e) => {
                        const v = e.target.value.replace(/\D/g, '');
                        if (v.length > 1) return fill(v, i); // SMS autofill / paste into a box
                        if (!v) return;
                        setAt(i, v);
                        refs.current[i + 1]?.focus();
                    }}
                    onKeyDown={(e) => {
                        if (e.key === 'Backspace') {
                            e.preventDefault();
                            if (d) setAt(i, '');
                            else if (i > 0) { onChange(value.slice(0, i - 1)); refs.current[i - 1]?.focus(); }
                        } else if (e.key === 'ArrowLeft' && i > 0) refs.current[i - 1]?.focus();
                        else if (e.key === 'ArrowRight' && i < length - 1) refs.current[i + 1]?.focus();
                    }}
                    onPaste={(e) => { e.preventDefault(); fill(e.clipboardData.getData('text'), i); }}
                    onFocus={(e) => e.target.select()} />
            ))}
        </div>
    );
}
