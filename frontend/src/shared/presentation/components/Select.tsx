import React, { useEffect, useLayoutEffect, useRef, useState } from 'react';
import { createPortal } from 'react-dom';
import { ChevronDown } from 'lucide-react';

/*
 * Oddiy <select> o'rnini bosadi. Brauzer ochilgan ro'yxatni o'zi chizadi —
 * to'rtburchak, uslubsiz va tungi rejimga mos emas; uni CSS bilan o'zgartirib
 * bo'lmaydi. Bu komponent xuddi <select> kabi ishlatiladi: `value`,
 * `onChange(e => e.target.value)`, ichida <option> lar. `className` tugmaga
 * beriladi, shuning uchun har sahifaning o'z ko'rinishi saqlanadi.
 *
 * Ro'yxat body'ga portal orqali chiziladi — `overflow-hidden` modal yoki
 * jadval ichida kesilib qolmaydi.
 */

export interface SelectChangeEvent { target: { value: string; name?: string } }

interface SelectProps {
  value?: string | number | null;
  onChange?: (e: SelectChangeEvent) => void;
  children?: React.ReactNode;
  className?: string;
  id?: string;
  name?: string;
  disabled?: boolean;
  required?: boolean;
  title?: string;
  'aria-label'?: string;
}

interface Option { value: string; label: React.ReactNode; disabled: boolean }

type OptionProps = { value?: string | number; children?: React.ReactNode; disabled?: boolean };

// <option> lar map() yoki fragment ichida ham bo'lishi mumkin.
const collectOptions = (children: React.ReactNode, out: Option[] = []): Option[] => {
  React.Children.forEach(children, child => {
    if (!React.isValidElement<OptionProps>(child)) return;
    if (child.type === React.Fragment) { collectOptions(child.props.children, out); return; }
    if (child.type !== 'option') return;
    const { value, children: label, disabled } = child.props;
    out.push({ value: String(value ?? (typeof label === 'string' ? label : '')), label, disabled: Boolean(disabled) });
  });
  return out;
};

const LIST_MAX_HEIGHT = 288; // max-h-72

export const Select: React.FC<SelectProps> = ({ value, onChange, children, className = '', id, name, disabled, required, title, 'aria-label': ariaLabel }) => {
  const options = collectOptions(children);
  const current = value === null || value === undefined ? '' : String(value);
  // <select> kabi: qiymat hech bir variantga mos kelmasa, birinchisi ko'rinadi.
  const selected = options.find(o => o.value === current) ?? options[0];
  const [open, setOpen] = useState(false);
  const [active, setActive] = useState(0);
  const [pos, setPos] = useState<{ top: number; left: number; width: number; up: boolean } | null>(null);
  const button = useRef<HTMLButtonElement>(null);
  const list = useRef<HTMLUListElement>(null);

  const place = () => {
    const r = button.current?.getBoundingClientRect();
    if (!r) return;
    const up = window.innerHeight - r.bottom < Math.min(LIST_MAX_HEIGHT, options.length * 40) + 8 && r.top > window.innerHeight - r.bottom;
    setPos({ top: up ? r.top - 4 : r.bottom + 4, left: r.left, width: r.width, up });
  };

  useLayoutEffect(() => { if (open) place(); }, [open]); // eslint-disable-line react-hooks/exhaustive-deps

  useEffect(() => {
    if (!open) return;
    const close = (e: MouseEvent) => {
      const target = e.target as Node;
      if (!button.current?.contains(target) && !list.current?.contains(target)) setOpen(false);
    };
    // Sahifa aylantirilsa yoki o'lchami o'zgarsa ro'yxat tugmadan ajralib qolmasin.
    const follow = (e: Event) => { if (!list.current?.contains(e.target as Node)) place(); };
    document.addEventListener('mousedown', close);
    window.addEventListener('scroll', follow, true);
    window.addEventListener('resize', follow);
    return () => {
      document.removeEventListener('mousedown', close);
      window.removeEventListener('scroll', follow, true);
      window.removeEventListener('resize', follow);
    };
  }, [open]); // eslint-disable-line react-hooks/exhaustive-deps

  useEffect(() => {
    if (open) list.current?.children[active]?.scrollIntoView({ block: 'nearest' });
  }, [open, active]);

  const pick = (o: Option) => {
    if (o.disabled) return;
    setOpen(false);
    if (o.value !== current) onChange?.({ target: { value: o.value, name } });
  };

  const move = (from: number, step: number) => {
    for (let i = from + step; i >= 0 && i < options.length; i += step) if (!options[i].disabled) return i;
    return from;
  };

  const onKeyDown = (e: React.KeyboardEvent) => {
    if (e.key === 'Escape' || e.key === 'Tab') { setOpen(false); return; }
    if (!open) {
      if (['ArrowDown', 'ArrowUp', 'Enter', ' '].includes(e.key)) {
        e.preventDefault();
        setActive(Math.max(0, options.findIndex(o => o.value === selected?.value)));
        setOpen(true);
      }
      return;
    }
    if (e.key === 'ArrowDown') { e.preventDefault(); setActive(i => move(i, 1)); }
    else if (e.key === 'ArrowUp') { e.preventDefault(); setActive(i => move(i, -1)); }
    else if (e.key === 'Home') { e.preventDefault(); setActive(move(-1, 1)); }
    else if (e.key === 'End') { e.preventDefault(); setActive(move(options.length, -1)); }
    else if ((e.key === 'Enter' || e.key === ' ') && options[active]) { e.preventDefault(); pick(options[active]); }
  };

  return (
    <>
      <button
        ref={button}
        type="button"
        id={id}
        title={title}
        aria-label={ariaLabel}
        aria-haspopup="listbox"
        aria-expanded={open}
        disabled={disabled}
        onClick={() => {
          setActive(Math.max(0, options.findIndex(o => o.value === selected?.value)));
          setOpen(o => !o);
        }}
        onKeyDown={onKeyDown}
        className={`${className} inline-flex items-center justify-between gap-2 text-left disabled:cursor-not-allowed disabled:opacity-60`}
      >
        <span className="min-w-0 flex-1 truncate">{selected?.label}</span>
        <ChevronDown aria-hidden className={`h-4 w-4 shrink-0 opacity-60 transition-transform ${open ? 'rotate-180' : ''}`} />
      </button>
      {/* Brauzerning "maydonni to'ldiring" tekshiruvi ishlashi uchun */}
      {required && (
        <input tabIndex={-1} aria-hidden required value={current} onChange={() => undefined} className="sr-only"
          onFocus={() => button.current?.focus()} />
      )}
      {open && pos && createPortal(
        <ul
          ref={list}
          role="listbox"
          aria-labelledby={id}
          onMouseDown={e => e.preventDefault()}
          style={{ top: pos.top, left: pos.left, minWidth: pos.width, transform: pos.up ? 'translateY(-100%)' : undefined }}
          className="fixed z-[1000] max-h-72 max-w-[min(32rem,calc(100vw-1rem))] overflow-auto scrollbar-none rounded-xl border border-slate-200 bg-white p-1 text-sm text-slate-800 shadow-xl dark:border-slate-700 dark:bg-slate-900 dark:text-slate-100"
        >
          {options.map((o, i) => (
            <li
              key={`${o.value}-${i}`}
              role="option"
              aria-selected={o.value === selected?.value}
              aria-disabled={o.disabled || undefined}
              onMouseEnter={() => setActive(i)}
              onClick={() => pick(o)}
              className={`rounded-lg px-3 py-2 ${o.disabled ? 'cursor-not-allowed opacity-50' : 'cursor-pointer'} ${i === active && !o.disabled ? 'bg-slate-100 dark:bg-slate-800' : ''} ${o.value === selected?.value ? 'font-semibold text-brand-600 dark:text-brand-400' : ''}`}
            >
              {o.label}
            </li>
          ))}
        </ul>,
        document.body,
      )}
    </>
  );
};
