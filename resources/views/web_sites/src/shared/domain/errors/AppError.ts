export class AppError extends Error {
  constructor(
    message: string,
    public readonly statusCode: number = 500,
    public readonly code?: string
  ) {
    super(message);
    this.name = 'AppError';
    Object.setPrototypeOf(this, new.target.prototype);
  }

  static badRequest(message: string): AppError {
    return new AppError(message, 400, 'BAD_REQUEST');
  }

  static unauthorized(message = 'Unauthorized access'): AppError {
    return new AppError(message, 401, 'UNAUTHORIZED');
  }

  static notFound(message = 'Resource not found'): AppError {
    return new AppError(message, 404, 'NOT_FOUND');
  }
}
