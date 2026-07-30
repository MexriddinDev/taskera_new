import React from 'react';

export const TaskSkeleton: React.FC = () => {
  return (
    <div className="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-4">
      {Array.from({ length: 6 }).map((_, i) => (
        <div
          key={i}
          className="p-5 bg-white dark:bg-gray-800 rounded-xl border border-gray-100 dark:border-gray-700 shadow-sm animate-pulse space-y-4"
        >
          <div className="flex justify-between items-center">
            <div className="h-5 bg-gray-200 dark:bg-gray-700 rounded w-20" />
            <div className="h-5 bg-gray-200 dark:bg-gray-700 rounded w-16" />
          </div>
          <div className="h-6 bg-gray-200 dark:bg-gray-700 rounded w-3/4" />
          <div className="h-4 bg-gray-200 dark:bg-gray-700 rounded w-1/2" />
          <div className="flex justify-between items-center pt-2 border-t border-gray-100 dark:border-gray-700">
            <div className="h-4 bg-gray-200 dark:bg-gray-700 rounded w-24" />
            <div className="h-8 bg-gray-200 dark:bg-gray-700 rounded w-8" />
          </div>
        </div>
      ))}
    </div>
  );
};
