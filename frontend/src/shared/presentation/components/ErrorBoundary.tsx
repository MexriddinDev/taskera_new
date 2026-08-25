import { Component, ErrorInfo, ReactNode } from 'react';
import { AlertTriangle, RefreshCw } from 'lucide-react';
import { Button } from './Button';
import { useT, type TFunction } from '../i18n/i18n';

interface Props {
  children: ReactNode;
}

interface State {
  hasError: boolean;
  error: Error | null;
}

class ErrorBoundaryClass extends Component<Props & { t: TFunction }, State> {
  public state: State = {
    hasError: false,
    error: null,
  };

  public static getDerivedStateFromError(error: Error): State {
    return { hasError: true, error };
  }

  public componentDidCatch(error: Error, errorInfo: ErrorInfo) {
    console.error('Uncaught error in ErrorBoundary:', error, errorInfo);
  }

  private handleReload = () => {
    window.location.reload();
  };

  public render() {
    if (this.state.hasError) {
      const t = this.props.t;
      return (
        <div className="min-h-screen flex items-center justify-center p-4 bg-gray-50 dark:bg-gray-900">
          <div className="max-w-md w-full p-8 bg-white dark:bg-gray-800 rounded-xl shadow-lg border border-gray-200 dark:border-gray-700 text-center">
            <div className="w-14 h-14 bg-red-100 dark:bg-red-900/30 text-red-600 dark:text-red-400 rounded-full flex items-center justify-center mx-auto mb-4">
              <AlertTriangle className="w-8 h-8" />
            </div>
            <h2 className="text-xl font-bold text-gray-900 dark:text-gray-100 mb-2">{t('errorBoundary.title')}</h2>
            <p className="text-sm text-gray-600 dark:text-gray-400 mb-6">
              {this.state.error?.message || t('errorBoundary.subtitle')}
            </p>
            <Button onClick={this.handleReload} leftIcon={<RefreshCw className="w-4 h-4" />}>
              {t('errorBoundary.reload')}
            </Button>
          </div>
        </div>
      );
    }

    return this.props.children;
  }
}

export const ErrorBoundary: React.FC<Props> = (props) => {
  const t = useT();
  return <ErrorBoundaryClass {...props} t={t} />;
};
