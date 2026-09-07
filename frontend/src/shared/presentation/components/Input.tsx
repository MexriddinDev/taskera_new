import React, { forwardRef, useId, useState } from 'react';
import { clsx } from 'clsx';
import { Eye, EyeOff } from 'lucide-react';

interface InputProps extends React.InputHTMLAttributes<HTMLInputElement> {
  label?: string;
  error?: string;
  helperText?: string;
  icon?: React.ReactNode;
  /** Ixchamroq variant: kichikroq yorliq va past padding (login sahifasi). */
  compact?: boolean;
}

export const Input = forwardRef<HTMLInputElement, InputProps>(
  ({ label, error, helperText, icon, className, id, compact = false, ...props }, ref) => {
    const generatedId = useId();
    const inputId = id || `field-${generatedId.replace(/:/g, '')}`;
    const descriptionId = `${inputId}-description`;

    const [showPassword, setShowPassword] = useState(false);
    const isPassword = props.type === 'password';
    const inputType = isPassword ? (showPassword ? 'text' : 'password') : props.type;

    return (
      <div className="w-full">
        {label && (
          <label
            htmlFor={inputId}
            className={clsx(
              'block font-medium text-gray-700 dark:text-gray-300 mb-1',
              compact ? 'text-xs' : 'text-sm'
            )}
          >
            {label}
          </label>
        )}
        <div className="relative rounded-md shadow-sm flex items-center">
          {icon && (
            <div className="absolute inset-y-0 left-0 pl-3 flex items-center pointer-events-none text-gray-400">
              {icon}
            </div>
          )}
          <input
            id={inputId}
            ref={ref}
            aria-invalid={Boolean(error)}
            aria-describedby={(error || helperText) ? descriptionId : undefined}
            className={clsx(
              'block w-full rounded-lg border text-sm transition-colors duration-200 bg-white dark:bg-gray-800 text-gray-900 dark:text-gray-100 placeholder-gray-400 focus:outline-none focus:ring-2 focus:ring-offset-0',
              icon ? 'pl-10' : 'pl-3.5',
              isPassword ? 'pr-10' : 'pr-3.5',
              compact ? 'py-2' : 'py-2.5',
              error
                ? 'border-red-500 focus:border-red-500 focus:ring-red-500/20'
                : 'border-gray-300 dark:border-gray-700 focus:border-brand-500 focus:ring-brand-500/20',
              className
            )}
            {...props}
            type={inputType}
          />
          {isPassword && (
            <button
              type="button"
              onClick={() => setShowPassword(!showPassword)}
              className="absolute inset-y-0 right-0 px-3 flex items-center text-gray-400 hover:text-gray-600 dark:hover:text-gray-300 focus:outline-none focus-visible:ring-2 focus-visible:ring-inset focus-visible:ring-brand-500 rounded-r-lg"
              aria-label={showPassword ? 'Parolni yashirish' : 'Parolni ko‘rsatish'}
              aria-pressed={showPassword}
            >
              {showPassword ? <EyeOff className="w-4 h-4" /> : <Eye className="w-4 h-4" />}
            </button>
          )}
        </div>
        {error ? (
          <p id={descriptionId} role="alert" className="mt-1 text-xs text-red-600 dark:text-red-400">{error}</p>
        ) : helperText ? (
          <p id={descriptionId} className="mt-1 text-xs text-gray-500 dark:text-gray-400">{helperText}</p>
        ) : null}
      </div>
    );
  }
);

Input.displayName = 'Input';
